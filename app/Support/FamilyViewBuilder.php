<?php

namespace App\Support;

use App\User;
use Illuminate\Support\Collection;

class FamilyViewBuilder
{
    public function loadChartRelations(User $user): User
    {
        $user->loadMissing([
            'father',
            'mother',
            'father.father',
            'father.mother',
            'mother.father',
            'mother.mother',
            'couples',
            'childs',
            'childs.father',
            'childs.mother',
            'childs.couples',
            'childs.childs',
            'childs.childs.father',
            'childs.childs.mother',
        ]);

        $siblings = $user->siblings();
        if ($siblings instanceof Collection && $siblings->isNotEmpty()) {
            $siblings->loadMissing([
                'couples',
                'childs',
                'childs.father',
                'childs.mother',
                'childs.couples',
                'childs.childs',
                'childs.childs.father',
                'childs.childs.mother',
            ]);
        }

        return $user;
    }

    public function buildChartData(User $user): array
    {
        $rootFamily = $this->buildFamilyCard($user, true);
        $siblings = $user->siblings() instanceof Collection ? $user->siblings() : collect($user->siblings());

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
        $relations = ['couples'];
        $prefix = '';

        for ($level = 0; $level < $maxDepth; $level++) {
            $segment = $prefix.'childs';
            $relations[] = $segment;
            $relations[] = $segment.'.father';
            $relations[] = $segment.'.mother';
            $relations[] = $segment.'.couples';
            $prefix = $segment.'.';
        }

        $user->loadMissing(array_values(array_unique($relations)));

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
            'spouse_labels' => $this->partnerCandidates($user)->values(),
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
            'spouse_labels' => $this->partnerCandidates($user)->values(),
            'children' => $children,
        ];
    }

    private function sortedChildren(User $user): Collection
    {
        return $user->childs->sortBy(function (User $child) {
            return [$child->birth_order ?? 999, $child->name];
        })->values();
    }

    private function familyGroups(User $user, bool $includeFallbackGroup): Collection
    {
        $groups = $this->partnerCandidates($user)->mapWithKeys(function (User $spouse) {
            return [$spouse->id => $this->emptyFamilyGroup($spouse, false)];
        });

        $fallbackGroup = $this->emptyFamilyGroup(null, true);
        $hasMappedChildren = false;

        foreach ($this->sortedChildren($user) as $child) {
            $partner = $user->gender_id == 1 ? $child->mother : $child->father;
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
                ? $user->name.' & '.$group['spouse']->name
                : $user->name;

            return $group;
        });

        if ($fallbackGroup['children']->isNotEmpty() || (!$hasMappedChildren && $groups->isEmpty())) {
            $fallbackGroup['label'] = $fallbackGroup['children']->isNotEmpty()
                ? trans('app.family_branch_unmapped')
                : $user->name;
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
            'spouse_labels' => $this->partnerCandidates($child)->values(),
            'grandchild_groups' => $this->familyGroups($child, false),
        ];
    }

    private function partnerCandidates(User $user): Collection
    {
        $partners = collect();

        foreach ($user->couples as $spouse) {
            if ($spouse && !$partners->has($spouse->id)) {
                $partners->put($spouse->id, $spouse);
            }
        }

        foreach ($user->childs as $child) {
            $partner = $user->gender_id == 1 ? $child->mother : $child->father;

            if ($partner && !$partners->has($partner->id)) {
                $partners->put($partner->id, $partner);
            }
        }

        return $partners->sortBy('name');
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
