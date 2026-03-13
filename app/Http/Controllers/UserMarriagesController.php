<?php

namespace App\Http\Controllers;

use App\Services\FamilyScopeResolver;
use App\User;

class UserMarriagesController extends Controller
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
    }

    /**
     * Show user marriage list.
     *
     * @param  \App\User  $user
     * @return \Illuminate\View\View
     */
    public function index(User $user)
    {
        if ($this->familyScopeResolver->hasActiveScope()
            && !(auth()->check() && is_system_admin(auth()->user()))
            && !$this->familyScopeResolver->isVisibleUser($user)) {
            abort(404);
        }

        $marriages = $user->marriages()->with('husband', 'wife')
            ->withCount('childs')
            ->get()
            ->filter(function ($marriage) {
                if (!$this->familyScopeResolver->hasActiveScope()) {
                    return true;
                }

                return (!$marriage->husband || $this->familyScopeResolver->isVisibleUser($marriage->husband))
                    && (!$marriage->wife || $this->familyScopeResolver->isVisibleUser($marriage->wife));
            })
            ->values();

        return view('users.marriages', compact('user', 'marriages'));
    }
}
