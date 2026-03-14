<?php

namespace App\Http\Controllers;

use App\Couple;
use App\Services\FamilyScopeResolver;
use Illuminate\Http\Request;

class CouplesController extends Controller
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
    }

    /**
     * Display the specified Couple.
     *
     * @param  \App\Couple  $couple
     * @return \Illuminate\View\View
     */
    public function show(Couple $couple)
    {
        $this->abortIfCoupleOutsideTenant($couple);
        $couple->loadCount('childs');

        return view('couples.show', compact('couple'));
    }

    /**
     * Show the form for editing the specified Couple.
     *
     * @param  \App\Couple  $couple
     * @return \Illuminate\View\View
     */
    public function edit(Couple $couple)
    {
        $this->authorize('edit', $couple);
        $this->abortIfCoupleOutsideTenant($couple);
        $couple->loadCount('childs');

        return view('couples.edit', compact('couple'));
    }

    /**
     * Update the specified Couple in storage.
     *
     * @param  \App\Couple  $couple
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Couple $couple)
    {
        $this->authorize('edit', $couple);
        $this->abortIfCoupleOutsideTenant($couple);

        $coupleData = request()->validate([
            'marriage_date' => 'nullable|date|date_format:Y-m-d',
            'divorce_date'  => 'nullable|date|date_format:Y-m-d',
            'spouse_order'  => 'nullable|integer|min:1',
        ]);

        $couple->marriage_date = $coupleData['marriage_date'];
        $couple->divorce_date = $coupleData['divorce_date'];
        $couple->spouse_order = $coupleData['spouse_order'] ?? null;
        $couple->save();

        return redirect()->route('couples.show', $couple);
    }

    /**
     * Remove the specified Couple from storage.
     *
     * @param  \App\Couple  $couple
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Couple $couple)
    {
        $this->authorize('delete', $couple);
        $this->abortIfCoupleOutsideTenant($couple);
        $couple->loadCount('childs');

        if ($couple->childs_count > 0) {
            return back()->withErrors([
                'couple' => __('couple.delete_blocked_childs'),
            ]);
        }

        $redirectUser = $couple->husband ?: $couple->wife;
        $couple->delete();

        if ($redirectUser) {
            return redirect()->route('users.marriages', $redirectUser)
                ->with('status', __('couple.deleted'));
        }

        return redirect()->route('users.search')
            ->with('status', __('couple.deleted'));
    }

    private function abortIfCoupleOutsideTenant(Couple $couple): void
    {
        if (!$this->familyScopeResolver->hasActiveScope()) {
            return;
        }

        $couple->loadMissing(['husband', 'wife']);

        if (
            ($couple->husband && !$this->familyScopeResolver->isVisibleUser($couple->husband)) ||
            ($couple->wife && !$this->familyScopeResolver->isVisibleUser($couple->wife))
        ) {
            abort(404);
        }
    }
}
