<?php

namespace App\Http\Controllers;

use App\Services\FamilyScopeResolver;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ClaimRegistrationController extends Controller
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
        $this->middleware('guest');
    }

    public function store(Request $request, User $user)
    {
        $this->abortIfUserOutsideScope($user);

        if ($user->email) {
            return back()->withErrors([
                'email' => trans('auth.claim_unavailable'),
            ]);
        }

        if (!$user->dob) {
            return back()->withErrors([
                'dob' => trans('auth.claim_birthdate_missing'),
            ]);
        }

        $validated = $request->validate([
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'dob' => 'required|date',
        ]);

        if ($validated['dob'] !== $user->dob->format('Y-m-d')) {
            return back()->withErrors([
                'dob' => trans('auth.claim_birthdate_mismatch'),
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        $user->forceFill([
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
        ])->save();

        Auth::login($user);

        return redirect()->route('home')->with('status', trans('auth.claim_success'));
    }

    private function abortIfUserOutsideScope(User $user): void
    {
        if (!$this->familyScopeResolver->hasActiveScope()) {
            return;
        }

        if (!$this->familyScopeResolver->isVisibleUser($user)) {
            abort(404);
        }
    }
}
