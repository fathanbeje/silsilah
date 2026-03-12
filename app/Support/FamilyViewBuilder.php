<?php

namespace App\Support;

use App\Services\FamilyScopeResolver;
use App\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class FamilyViewBuilder
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
    }

    public function loadChartRelations(User $user): User
    {
        $user->loadMissing([
            'father',
            'mother',
            'father.father',
            'father.mother',
            'mother.father',
            'mother.mother',
        ]);

        $this->hydrateUserGraph(new EloquentCollection([$user]), 2);

        $siblings = $this->filterUsers($user->siblings());
        if ($siblings instanceof Collection && $siblings->isNotEmpty()) {
            $this->hydrateUserGraph(new EloquentCollection($siblings->all()), 2);
        }

        return $user;
    }

    public function buildChartData(User $user): array
    {
        $rootFamily = $this->buildFamilyCard($user, true);
        $siblings = $user->siblings() instanceof Collection
            ? $this->filterUsers($user->siblings())
            : collect($user->siblings());

        return [
            'familyGroups' => $rootFamily['family_groups'],
            'rootSpouseLabels' => $rootFamily['spouse_labels'],
            'siblingFamilyCards' => $siblings->map(function (User $sibling) {
                return $this->buildFamilyCard($sibling, false);
            })->values(),
        ];
    }

    public function loadTreeRelations(User $user, int $maxDepth = 6): User
    {
        $this->hydrateUserGraph(new EloquentCollection([$user]), $maxDepth - 1);

        return $user;
    }

    public function buildTreeData(User $user, int $maxDepth = 6): array
    {
        $generationCounts = array_fill(1, $maxDepth, 0);

        return [
            'node' => $this->buildTreeNode($user, 1, $maxDepth, $generationCounts),
            'generationCounts' => $generationCounts,
        ];
    }

    public function buildFamilyCard(User $user, bool $includeFallbackGroup): array
    {
        return [
            'user' => $user,
            'spouse_labels' => $this->filterUsers($this->partnerCandidates($user))->values(),
            'family_groups' => $this->familyGroups($user, $includeFallbackGroup),
        ];
    }

    private function buildTreeNode(User $user, int $depth, int $maxDepth, array &$generationCounts): array
    {
        $children = collect();

        if ($depth < $maxDepth) {
            foreach ($this->sortedChildren($user) as $child) {
                $generationCounts[$depth] = ($generationCounts[$depth] ?? 0) + 1;
                $children->push(
                    $this->buildTreeNode($child, $depth + 1, $maxDepth, $generationCounts)
                );
            }
        }

        return [
            'user' => $user,
            'node_id' => $user->id,
            'node_depth' => $depth,
            'has_children' => $children->isNotEmpty(),
            'default_expanded' => $depth === 1,
            'spouse_labels' => $this->filterUsers($this->partnerCandidates($user))->values(),
            'children' => $children,
        ];
    }

    private function sortedChildren(User $user): Collection
    {
        $children = $user->relationLoaded('childs')
            ? $user->getRelation('childs')
            : $user->childs;

        return $this->filterUsers($children)->sortBy(function (User $child) {
            return [$child->birth_order ?? 999, $child->name];
        })->values();
    }

    private function hydrateUserGraph(EloquentCollection $users, int $depth): void
    {
        $users = new EloquentCollection(
            $users->filter()->unique('id')->values()->all()
        );

        if ($users->isEmpty()) {
            return;
        }

        $users->loadMissing(['father', 'mother', 'couples', 'marriages']);

        foreach ($users as $user) {
            if (! $user->relationLoaded('childs')) {
                $user->setRelation('childs', new EloquentCollection());
            }
        }

        if ($depth <= 0) {
            return;
        }

        $maleIds = $users->where('gender_id', 1)->pluck('id')->filter()->values();
        $femaleIds = $users->where('gender_id', 2)->pluck('id')->filter()->values();
        $marriageIds = $users
            ->flatMap(fn (User $user) => $user->marriageIds())
            ->filter()
            ->unique()
            ->values();

        if ($maleIds->isEmpty() && $femaleIds->isEmpty() && $marriageIds->isEmpty()) {
            return;
        }

        $childrenQuery = User::query()
            ->where(function ($query) use ($maleIds, $femaleIds, $marriageIds) {
                if ($maleIds->isNotEmpty()) {
                    $query->whereIn('father_id', $maleIds);
                }

                if ($femaleIds->isNotEmpty()) {
                    $method = $maleIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('mother_id', $femaleIds);
                }

                if ($marriageIds->isNotEmpty()) {
                    $method = ($maleIds->isNotEmpty() || $femaleIds->isNotEmpty()) ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('parent_id', $marriageIds);
                }
            })
            ->orderByRaw('COALESCE(birth_order, 999), name');

        $children = $this->familyScopeResolver->applyToUserQuery($childrenQuery)->get();

        $children->loadMissing(['father', 'mother', 'parent.husband', 'parent.wife', 'couples']);

        $assignedChildren = $users->mapWithKeys(function (User $user) {
            return [$user->id => collect()];
        });

        foreach ($children as $child) {
            $parent = $child->relationLoaded('parent') ? $child->getRelation('parent') : null;

            if ($parent) {
                if ($parent->husband_id && $assignedChildren->has($parent->husband_id)) {
                    $assignedChildren[$parent->husband_id]->put($child->id, $child);
                }

                if ($parent->wife_id && $assignedChildren->has($parent->wife_id)) {
                    $assignedChildren[$parent->wife_id]->put($child->id, $child);
                }

                continue;
            }

            if ($child->father_id && $assignedChildren->has($child->father_id)) {
                $assignedChildren[$child->father_id]->put($child->id, $child);
            }

            if ($child->mother_id && $assignedChildren->has($child->mother_id)) {
                $assignedChildren[$child->mother_id]->put($child->id, $child);
            }
        }

        foreach ($users as $user) {
            $user->setRelation(
                'childs',
                new EloquentCollection(
                    $assignedChildren->get($user->id, collect())->values()->all()
                )
            );
        }

        $this->hydrateUserGraph(new EloquentCollection($children->all()), $depth - 1);
    }

    private function familyGroups(User $user, bool $includeFallbackGroup): Collection
    {
        $groups = $this->filterUsers($this->partnerCandidates($user))->mapWithKeys(function (User $spouse) {
            return [$spouse->id => $this->emptyFamilyGroup($spouse, false)];
        });

        $fallbackGroup = $this->emptyFamilyGroup(null, true);
        $hasMappedChildren = false;

        foreach ($this->sortedChildren($user) as $child) {
            $partner = $user->spouseForChild($child);
            $key = $partner ? $partner->id : null;

            if ($key && !$groups->has($key)) {
                $groups->put($key, $this->emptyFamilyGroup($partner, false));
            }

            $childCard = $this->childCard($child);

            if ($key && $groups->has($key)) {
                $group = $groups->get($key);
                $group['children']->push($childCard);
                $groups->put($key, $group);
                $hasMappedChildren = true;
                continue;
            }

            $fallbackGroup['children']->push($childCard);
        }

        $groups = $groups->values()->map(function (array $group) use ($user) {
            $group['label'] = $group['spouse']
                ? $user->display_name.' & '.$group['spouse']->display_name
                : $user->display_name;

            return $group;
        });

        if ($fallbackGroup['children']->isNotEmpty() || (!$hasMappedChildren && $groups->isEmpty())) {
            $fallbackGroup['label'] = $fallbackGroup['children']->isNotEmpty()
                ? trans('app.family_branch_unmapped')
                : $user->display_name;
            $groups->push($fallbackGroup);
        }

        return $groups->filter(function (array $group) use ($includeFallbackGroup) {
            return $includeFallbackGroup || $group['children']->isNotEmpty();
        })->values();
    }

    private function childCard(User $child): array
    {
        return [
            'user' => $child,
            'spouse_labels' => $this->filterUsers($this->partnerCandidates($child))->values(),
            'grandchild_groups' => $this->familyGroups($child, false),
        ];
    }

    private function partnerCandidates(User $user): Collection
    {
        $partners = collect();

        foreach ($this->filterUsers($user->couples) as $spouse) {
            if ($spouse && !$partners->has($spouse->id)) {
                $partners->put($spouse->id, $spouse);
            }
        }

        foreach ($this->filterUsers($user->childs) as $child) {
            $partner = $user->spouseForChild($child);

            if ($partner && $this->familyScopeResolver->isVisibleUser($partner) && !$partners->has($partner->id)) {
                $partners->put($partner->id, $partner);
            }
        }

        return $partners->values();
    }

    private function filterUsers(Collection|EloquentCollection $users): Collection|EloquentCollection
    {
        if (!$this->familyScopeResolver->hasActiveScope()) {
            return $users;
        }

        $filtered = $this->familyScopeResolver->filterUsers(collect($users));

        if ($users instanceof EloquentCollection) {
            return new EloquentCollection($filtered->all());
        }

        return $filtered;
    }

    private function emptyFamilyGroup(?User $spouse, bool $isUnmapped): array
    {
        return [
            'spouse' => $spouse,
            'is_unmapped' => $isUnmapped,
            'children' => collect(),
            'label' => null,
        ];
    }
}
