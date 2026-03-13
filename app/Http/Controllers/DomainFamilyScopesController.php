<?php

namespace App\Http\Controllers;

use App\DomainFamilyScope;
use App\Services\FamilyScopeResolver;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomainFamilyScopesController extends Controller
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
    }

    public function index()
    {
        $scopes = $this->scopeDomainQuery(DomainFamilyScope::query())
            ->with('coreUser')
            ->orderBy('host')
            ->paginate(20);

        return view('domain-family-scopes.index', [
            'scopes' => $scopes,
            'currentHost' => $this->familyScopeResolver->currentHost(),
            'canCreateScope' => !$this->familyScopeResolver->hasActiveScope(),
        ]);
    }

    public function create()
    {
        $this->abortIfScopedTenantCannotCreate();

        return view('domain-family-scopes.create', [
            'scope' => new DomainFamilyScope(),
            'coreUserOptions' => $this->coreUserOptions(),
            'lockHost' => false,
        ]);
    }

    public function store(Request $request)
    {
        $this->abortIfScopedTenantCannotCreate();
        DomainFamilyScope::create($this->validatedData($request));

        return redirect()->route('domain-family-scopes.index')
            ->with('status', 'Scope domain berhasil ditambahkan.');
    }

    public function edit(DomainFamilyScope $domainFamilyScope)
    {
        $this->abortIfScopeOutsideCurrentTenant($domainFamilyScope);

        return view('domain-family-scopes.edit', [
            'scope' => $domainFamilyScope,
            'coreUserOptions' => $this->coreUserOptions(),
            'lockHost' => $this->familyScopeResolver->hasActiveScope(),
        ]);
    }

    public function update(Request $request, DomainFamilyScope $domainFamilyScope)
    {
        $this->abortIfScopeOutsideCurrentTenant($domainFamilyScope);
        $domainFamilyScope->update($this->validatedData($request, $domainFamilyScope));

        return redirect()->route('domain-family-scopes.index')
            ->with('status', 'Scope domain berhasil diperbarui.');
    }

    public function destroy(DomainFamilyScope $domainFamilyScope)
    {
        $this->abortIfScopeOutsideCurrentTenant($domainFamilyScope);
        $domainFamilyScope->delete();

        return redirect()->route('domain-family-scopes.index')
            ->with('status', 'Scope domain berhasil dihapus.');
    }

    private function validatedData(Request $request, ?DomainFamilyScope $scope = null): array
    {
        $rules = [
            'host' => [
                'required',
                'string',
                'max:255',
                Rule::unique('domain_family_scopes', 'host')->ignore($scope?->id),
            ],
            'core_user_id' => 'required|exists:users,id',
            'is_active' => 'nullable|boolean',
        ];

        if ($this->familyScopeResolver->hasActiveScope()) {
            $rules['host'][] = Rule::in([$this->familyScopeResolver->currentHost()]);

            $visibleIds = $this->familyScopeResolver->visibleUserIds();
            $rules['core_user_id'][] = Rule::in($visibleIds);
        }

        $validated = $request->validate($rules);

        return [
            'host' => strtolower(trim($validated['host'])),
            'core_user_id' => $validated['core_user_id'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    private function coreUserOptions(): array
    {
        return $this->familyScopeResolver->applyToUserQuery(User::query())
            ->orderBy('name')
            ->get(['id', 'name', 'nickname'])
            ->mapWithKeys(function (User $user) {
                return [$user->id => $user->display_name.' / '.$user->nickname];
            })
            ->all();
    }

    private function scopeDomainQuery($query)
    {
        if (!$this->familyScopeResolver->hasActiveScope()) {
            return $query;
        }

        return $query->where('host', $this->familyScopeResolver->currentHost());
    }

    private function abortIfScopedTenantCannotCreate(): void
    {
        if ($this->familyScopeResolver->hasActiveScope()) {
            abort(404);
        }
    }

    private function abortIfScopeOutsideCurrentTenant(DomainFamilyScope $domainFamilyScope): void
    {
        if (
            $this->familyScopeResolver->hasActiveScope() &&
            $domainFamilyScope->host !== $this->familyScopeResolver->currentHost()
        ) {
            abort(404);
        }
    }
}
