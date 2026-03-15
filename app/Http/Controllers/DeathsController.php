<?php

namespace App\Http\Controllers;

use App\Services\DeathIndexBuilder;
use App\Services\FamilyScopeResolver;
use Illuminate\Http\Request;

class DeathsController extends Controller
{
    public function __construct(
        private DeathIndexBuilder $deathIndexBuilder,
        private FamilyScopeResolver $familyScopeResolver
    ) {
    }

    public function index(Request $request)
    {
        if (! $this->familyScopeResolver->publicAccessAllowed() || ! $this->familyScopeResolver->hasActiveScope()) {
            abort(404);
        }

        $tab = $request->query('tab', 'all');
        if (! in_array($tab, ['all', 'haul-bulan-ini'], true)) {
            $tab = 'all';
        }

        return view('deaths.index', $this->deathIndexBuilder->build($tab));
    }
}
