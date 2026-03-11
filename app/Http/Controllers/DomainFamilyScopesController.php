<?php

namespace App\Http\Controllers;

use App\DomainFamilyScope;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DomainFamilyScopesController extends Controller
{
    public function index()
    {
        $scopes = DomainFamilyScope::query()
            ->with('coreUser')
            ->orderBy('host')
            ->paginate(20);

        return view('domain-family-scopes.index', compact('scopes'));
    }

    public function create()
    {
        return view('domain-family-scopes.create', [
            'scope' => new DomainFamilyScope(),
            'coreUserOptions' => $this->coreUserOptions(),
        ]);
    }

    public function store(Request $request)
    {
        DomainFamilyScope::create($this->validatedData($request));

        return redirect()->route('domain-family-scopes.index')
            ->with('status', 'Scope domain berhasil ditambahkan.');
    }

    public function edit(DomainFamilyScope $domainFamilyScope)
    {
        return view('domain-family-scopes.edit', [
            'scope' => $domainFamilyScope,
            'coreUserOptions' => $this->coreUserOptions(),
        ]);
    }

    public function update(Request $request, DomainFamilyScope $domainFamilyScope)
    {
        $domainFamilyScope->update($this->validatedData($request, $domainFamilyScope));

        return redirect()->route('domain-family-scopes.index')
            ->with('status', 'Scope domain berhasil diperbarui.');
    }

    public function destroy(DomainFamilyScope $domainFamilyScope)
    {
        $domainFamilyScope->delete();

        return redirect()->route('domain-family-scopes.index')
            ->with('status', 'Scope domain berhasil dihapus.');
    }

    private function validatedData(Request $request, ?DomainFamilyScope $scope = null): array
    {
        $validated = $request->validate([
            'host' => [
                'required',
                'string',
                'max:255',
                Rule::unique('domain_family_scopes', 'host')->ignore($scope?->id),
            ],
            'core_user_id' => 'required|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        return [
            'host' => strtolower(trim($validated['host'])),
            'core_user_id' => $validated['core_user_id'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    private function coreUserOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'nickname'])
            ->mapWithKeys(function (User $user) {
                return [$user->id => $user->display_name.' / '.$user->nickname];
            })
            ->all();
    }
}
