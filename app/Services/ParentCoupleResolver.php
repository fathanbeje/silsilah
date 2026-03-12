<?php

namespace App\Services;

use App\Couple;
use App\User;
use Ramsey\Uuid\Uuid;

class ParentCoupleResolver
{
    public function describeParents(User $user): array
    {
        $parent = $user->relationLoaded('parent') ? $user->parent : $user->parent;

        if ($parent && ($parent->husband || $parent->wife)) {
            return [
                'father' => $parent->husband,
                'mother' => $parent->wife,
                'is_synced' => true,
            ];
        }

        $father = $user->relationLoaded('father') ? $user->father : $user->father;
        $mother = $user->relationLoaded('mother') ? $user->mother : $user->mother;

        return [
            'father' => $father,
            'mother' => $mother,
            'is_synced' => false,
        ];
    }

    public function syncUser(User $user): ?Couple
    {
        if (!$user->father_id || !$user->mother_id) {
            if ($user->parent_id !== null) {
                $user->parent_id = null;
                $user->save();
            }

            return null;
        }

        $father = $user->relationLoaded('father') ? $user->father : User::find($user->father_id);
        $mother = $user->relationLoaded('mother') ? $user->mother : User::find($user->mother_id);

        if (!$father || !$mother || (int) $father->gender_id !== 1 || (int) $mother->gender_id !== 2) {
            if ($user->parent_id !== null) {
                $user->parent_id = null;
                $user->save();
            }

            return null;
        }

        $couple = Couple::firstOrCreate(
            [
                'husband_id' => $father->id,
                'wife_id' => $mother->id,
            ],
            [
                'id' => Uuid::uuid4()->toString(),
                'manager_id' => $this->resolveManagerId($user, $father, $mother),
            ]
        );

        if ($user->parent_id !== $couple->id) {
            $user->parent_id = $couple->id;
            $user->save();
        }

        return $couple;
    }

    public function assignCouple(User $user, Couple $couple): void
    {
        $user->father_id = $couple->husband_id;
        $user->mother_id = $couple->wife_id;
        $user->parent_id = $couple->id;
        $user->save();
    }

    public function syncAllUsers(): array
    {
        $processed = 0;
        $updated = 0;
        $cleared = 0;

        User::query()
            ->where(function ($query) {
                $query->whereNotNull('father_id')
                    ->orWhereNotNull('mother_id')
                    ->orWhereNotNull('parent_id');
            })
            ->with(['father', 'mother', 'parent.husband', 'parent.wife'])
            ->orderBy('id')
            ->chunk(200, function ($users) use (&$processed, &$updated, &$cleared) {
                foreach ($users as $user) {
                    $processed++;
                    $before = $user->parent_id;
                    $couple = $this->syncUser($user);
                    $after = $user->fresh()->parent_id;

                    if ($before !== $after) {
                        if ($couple) {
                            $updated++;
                        } else {
                            $cleared++;
                        }
                    }
                }
            }, 'id');

        return compact('processed', 'updated', 'cleared');
    }

    private function resolveManagerId(User $user, User $father, User $mother): ?string
    {
        if (auth()->check()) {
            return auth()->id();
        }

        return $user->manager_id ?: $father->manager_id ?: $mother->manager_id;
    }
}
