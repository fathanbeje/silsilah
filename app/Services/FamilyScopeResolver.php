<?php

namespace App\Services;

use App\Couple;
use App\DomainFamilyScope;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FamilyScopeResolver
{
    private bool $scopeLoaded = false;
    private ?DomainFamilyScope $scope = null;
    private ?array $bloodUserIds = null;
    private ?array $visibleUserIds = null;

    public function __construct(private Request $request)
    {
    }

    public function currentScope(): ?DomainFamilyScope
    {
        if ($this->scopeLoaded) {
            return $this->scope;
        }

        $this->scopeLoaded = true;
        $host = $this->normalizeHost($this->request->getHost());

        if ($host === null) {
            return $this->scope = null;
        }

        $this->scope = DomainFamilyScope::query()
            ->with('coreUser')
            ->where('host', $host)
            ->where('is_active', true)
            ->first();

        return $this->scope;
    }

    public function hasActiveScope(): bool
    {
        return $this->currentScope() !== null;
    }

    public function coreUser(): ?User
    {
        return $this->currentScope()?->coreUser;
    }

    public function isVisibleUser(User|string|null $user): bool
    {
        if ($this->shouldBypassScope()) {
            return true;
        }

        if (!$this->hasActiveScope()) {
            return true;
        }

        $userId = $user instanceof User ? $user->id : $user;

        if (!$userId) {
            return false;
        }

        return in_array($userId, $this->visibleUserIds(), true);
    }

    public function applyToUserQuery(Builder $query): Builder
    {
        if ($this->shouldBypassScope()) {
            return $query;
        }

        if (!$this->hasActiveScope()) {
            return $query;
        }

        $ids = $this->visibleUserIds();

        if (empty($ids)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $ids);
    }

    public function visibleUserIds(): array
    {
        if ($this->visibleUserIds !== null) {
            return $this->visibleUserIds;
        }

        if (!$this->hasActiveScope()) {
            return $this->visibleUserIds = [];
        }

        $bloodIds = $this->bloodUserIds();
        $spouseIds = [];

        if (!empty($bloodIds)) {
            $couples = Couple::query()
                ->where(function ($query) use ($bloodIds) {
                    $query->whereIn('husband_id', $bloodIds)
                        ->orWhereIn('wife_id', $bloodIds);
                })
                ->get(['husband_id', 'wife_id']);

            foreach ($couples as $couple) {
                if (in_array($couple->husband_id, $bloodIds, true) && $couple->wife_id) {
                    $spouseIds[] = $couple->wife_id;
                }

                if (in_array($couple->wife_id, $bloodIds, true) && $couple->husband_id) {
                    $spouseIds[] = $couple->husband_id;
                }
            }
        }

        $this->visibleUserIds = array_values(array_unique(array_merge($bloodIds, $spouseIds)));

        return $this->visibleUserIds;
    }

    public function filterUsers(Collection $users): Collection
    {
        if ($this->shouldBypassScope()) {
            return $users->values();
        }

        if (!$this->hasActiveScope()) {
            return $users;
        }

        $visibleIds = array_flip($this->visibleUserIds());

        return $users->filter(function ($user) use ($visibleIds) {
            return isset($visibleIds[$user instanceof User ? $user->id : $user]);
        })->values();
    }

    public function bloodUserIds(): array
    {
        if ($this->bloodUserIds !== null) {
            return $this->bloodUserIds;
        }

        $coreUser = $this->coreUser();

        if (!$coreUser) {
            return $this->bloodUserIds = [];
        }

        $bloodIds = [$coreUser->id];
        $pendingIds = [$coreUser->id];

        while (!empty($pendingIds)) {
            $coupleIds = Couple::query()
                ->where(function ($query) use ($pendingIds) {
                    $query->whereIn('husband_id', $pendingIds)
                        ->orWhereIn('wife_id', $pendingIds);
                })
                ->pluck('id')
                ->filter()
                ->values()
                ->all();

            $childrenQuery = User::query()->where(function ($query) use ($pendingIds, $coupleIds) {
                $query->whereIn('father_id', $pendingIds)
                    ->orWhereIn('mother_id', $pendingIds);

                if (!empty($coupleIds)) {
                    $query->orWhereIn('parent_id', $coupleIds);
                }
            });

            $newIds = $childrenQuery->pluck('id')
                ->filter()
                ->reject(fn ($id) => in_array($id, $bloodIds, true))
                ->values()
                ->all();

            if (empty($newIds)) {
                break;
            }

            $bloodIds = array_values(array_unique(array_merge($bloodIds, $newIds)));
            $pendingIds = $newIds;
        }

        return $this->bloodUserIds = $bloodIds;
    }

    private function normalizeHost(?string $host): ?string
    {
        $host = strtolower(trim((string) $host));

        return $host !== '' ? $host : null;
    }

    private function shouldBypassScope(): bool
    {
        return auth()->check() && is_system_admin(auth()->user());
    }
}
