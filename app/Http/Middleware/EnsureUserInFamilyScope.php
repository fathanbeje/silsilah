<?php

namespace App\Http\Middleware;

use App\Services\FamilyScopeResolver;
use Closure;
use Illuminate\Http\Request;

class EnsureUserInFamilyScope
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->route('user');

        if (!$user || !$this->familyScopeResolver->hasActiveScope()) {
            return $next($request);
        }

        if (auth()->check() && is_system_admin(auth()->user())) {
            return $next($request);
        }

        if (!$this->familyScopeResolver->isVisibleUser($user)) {
            abort(404);
        }

        return $next($request);
    }
}
