<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BirthOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->get('q'));
        $showAll = $request->boolean('all');

        $familyGroups = $this->buildFamilyGroups($query, $showAll);

        return view('birth-orders.index', [
            'familyGroups' => $familyGroups,
            'query' => $query,
            'showAll' => $showAll,
            'totalMissingUsers' => User::query()->whereNull('birth_order')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'family_key' => 'required|string',
            'children' => 'required|array',
        ]);

        $children = collect($request->input('children', []))
            ->map(function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }

                return (int) $value;
            });

        foreach ($children->filter() as $birthOrder) {
            if ($birthOrder < 1) {
                return back()->withErrors(['children' => __('app.birth_order_minimum')]);
            }
        }

        if ($children->filter()->duplicates()->isNotEmpty()) {
            return back()->withErrors(['children' => __('app.birth_order_must_be_unique')]);
        }

        $users = User::query()->whereIn('id', $children->keys()->all())->get();
        if ($users->isEmpty()) {
            return back()->withErrors(['children' => __('app.birth_order_family_not_found')]);
        }

        $requestedFamilyKey = $request->get('family_key');
        foreach ($users as $user) {
            if ($this->familyKey($user) !== $requestedFamilyKey) {
                return back()->withErrors(['children' => __('app.birth_order_family_not_found')]);
            }
        }

        DB::transaction(function () use ($users, $children) {
            foreach ($users as $user) {
                $user->birth_order = $children->get($user->id);
                $user->save();
            }
        });

        return back()->with('status', __('app.birth_order_saved'));
    }

    private function buildFamilyGroups(string $query = '', bool $showAll = false): Collection
    {
        $users = User::query()
            ->where(function ($query) {
                $query->whereNotNull('father_id')
                    ->orWhereNotNull('mother_id')
                    ->orWhereNotNull('parent_id');
            })
            ->with([
                'father:id,name',
                'mother:id,name',
                'parent.husband:id,name',
                'parent.wife:id,name',
            ])
            ->get();

        return $users
            ->groupBy(fn (User $user) => $this->familyKey($user))
            ->map(function (Collection $members, string $familyKey) {
                $members = $members->sort(function (User $a, User $b) {
                    $aRank = $a->birth_order === null ? PHP_INT_MAX : $a->birth_order;
                    $bRank = $b->birth_order === null ? PHP_INT_MAX : $b->birth_order;

                    if ($aRank === $bRank) {
                        return strcmp((string) $a->name, (string) $b->name);
                    }

                    return $aRank <=> $bRank;
                })->values();

                $firstMember = $members->first();
                $fatherName = optional($firstMember->father)->name ?: optional(optional($firstMember->parent)->husband)->name;
                $motherName = optional($firstMember->mother)->name ?: optional(optional($firstMember->parent)->wife)->name;

                return [
                    'key' => $familyKey,
                    'father_name' => $fatherName,
                    'mother_name' => $motherName,
                    'children' => $members,
                    'missing_count' => $members->whereNull('birth_order')->count(),
                ];
            })
            ->filter(function (array $family) use ($query, $showAll) {
                if (!$showAll && $family['missing_count'] === 0) {
                    return false;
                }

                if ($query === '') {
                    return true;
                }

                $haystacks = [
                    strtolower((string) $family['father_name']),
                    strtolower((string) $family['mother_name']),
                ];

                foreach ($family['children'] as $child) {
                    $haystacks[] = strtolower((string) $child->name);
                }

                $queryLower = strtolower($query);

                foreach ($haystacks as $haystack) {
                    if (str_contains($haystack, $queryLower)) {
                        return true;
                    }
                }

                return false;
            })
            ->sortByDesc('missing_count')
            ->values();
    }

    private function familyKey(User $user): string
    {
        return implode('|', [
            $user->father_id ?: 'null',
            $user->mother_id ?: 'null',
            $user->parent_id ?: 'null',
        ]);
    }
}
