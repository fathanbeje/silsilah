<?php

namespace App\Http\Controllers;

use App\Couple;
use App\Services\FamilyScopeResolver;
use App\Services\ParentCoupleResolver;
use App\User;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class FamilyActionsController extends Controller
{
    public function __construct(
        private ParentCoupleResolver $parentCoupleResolver,
        private FamilyScopeResolver $familyScopeResolver
    )
    {
    }

    /**
     * Set father for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setFather(Request $request, User $user)
    {
        $this->authorize('edit', $user);
        $this->abortIfUserOutsideTenant($user);

        $request->validate([
            'set_father_id' => 'nullable',
            'set_father'    => 'required_without:set_father_id|max:255',
        ]);

        if ($request->get('set_father_id')) {
            $father = User::findOrFail($request->get('set_father_id'));
            $this->abortIfUserOutsideTenant($father);
            $user->father_id = $father->id;
            $user->save();
        } else {
            $father = new User;
            $father->id = Uuid::uuid4()->toString();
            $father->name = $request->get('set_father');
            $father->nickname = $request->get('set_father');
            $father->gender_id = 1;
            $father->manager_id = auth()->id();

            $user->setFather($father);
        }

        $this->parentCoupleResolver->syncUser($user);

        return back();
    }

    /**
     * Set mother for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setMother(Request $request, User $user)
    {
        $this->authorize('edit', $user);
        $this->abortIfUserOutsideTenant($user);

        $request->validate([
            'set_mother_id' => 'nullable',
            'set_mother'    => 'required_without:set_mother_id|max:255',
        ]);

        if ($request->get('set_mother_id')) {
            $mother = User::findOrFail($request->get('set_mother_id'));
            $this->abortIfUserOutsideTenant($mother);
            $user->mother_id = $mother->id;
            $user->save();
        } else {
            $mother = new User;
            $mother->id = Uuid::uuid4()->toString();
            $mother->name = $request->get('set_mother');
            $mother->nickname = $request->get('set_mother');
            $mother->gender_id = 2;
            $mother->manager_id = auth()->id();

            $user->setMother($mother);
        }

        $this->parentCoupleResolver->syncUser($user);

        return back();
    }

    /**
     * Add child for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addChild(Request $request, User $user)
    {
        $this->authorize('edit', $user);
        $this->abortIfUserOutsideTenant($user);

        $request->validate([
            'add_child_name'        => 'required|string|max:255',
            'add_child_gender_id'   => 'required|in:1,2',
            'add_child_parent_id'   => 'nullable|exists:couples,id',
            'add_child_birth_order' => 'nullable|numeric',
        ]);

        $child = new User;
        $child->id = Uuid::uuid4()->toString();
        $child->name = $request->get('add_child_name');
        $child->nickname = $request->get('add_child_name');
        $child->gender_id = $request->get('add_child_gender_id');
        $child->parent_id = $request->get('add_child_parent_id');
        $child->birth_order = $request->get('add_child_birth_order');
        $child->manager_id = auth()->id();

        \DB::beginTransaction();
        $child->save();

        if ($request->get('add_child_parent_id')) {
            $couple = Couple::find($request->get('add_child_parent_id'));
            $this->abortIfCoupleOutsideTenant($couple);
            $this->parentCoupleResolver->assignCouple($child, $couple);
        } else {
            if ($user->gender_id == 1) {
                $child->setFather($user);
            } else {
                $child->setMother($user);
            }

            $this->parentCoupleResolver->syncUser($child);
        }

        \DB::commit();

        return back();
    }

    /**
     * Add wife for male user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addWife(Request $request, User $user)
    {
        $this->authorize('edit', $user);
        $this->abortIfUserOutsideTenant($user);

        $request->validate([
            'set_wife_id'   => 'nullable',
            'set_wife'      => 'required_without:set_wife_id|max:255',
            'marriage_date' => 'nullable|date|date_format:Y-m-d',
            'spouse_order'  => 'nullable|integer|min:1',
        ]);

        if ($request->get('set_wife_id')) {
            $wife = User::findOrFail($request->get('set_wife_id'));
            $this->abortIfUserOutsideTenant($wife);
        } else {
            $wife = new User;
            $wife->id = Uuid::uuid4()->toString();
            $wife->name = $request->get('set_wife');
            $wife->nickname = $request->get('set_wife');
            $wife->gender_id = 2;
            $wife->manager_id = auth()->id();
        }

        $spouseOrder = $request->filled('spouse_order') ? (int) $request->get('spouse_order') : null;
        $user->addWife($wife, $request->get('marriage_date'), $spouseOrder);

        return back();
    }

    /**
     * Add husband for female user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addHusband(Request $request, User $user)
    {
        $this->authorize('edit', $user);
        $this->abortIfUserOutsideTenant($user);

        $this->validate($request, [
            'set_husband_id' => 'nullable',
            'set_husband'    => 'required_without:set_husband_id|max:255',
            'marriage_date'  => 'nullable|date|date_format:Y-m-d',
            'spouse_order'   => 'nullable|integer|min:1',
        ]);

        if ($request->get('set_husband_id')) {
            $husband = User::findOrFail($request->get('set_husband_id'));
            $this->abortIfUserOutsideTenant($husband);
        } else {
            $husband = new User;
            $husband->id = Uuid::uuid4()->toString();
            $husband->name = $request->get('set_husband');
            $husband->nickname = $request->get('set_husband');
            $husband->gender_id = 1;
            $husband->manager_id = auth()->id();
        }

        $spouseOrder = $request->filled('spouse_order') ? (int) $request->get('spouse_order') : null;
        $user->addHusband($husband, $request->get('marriage_date'), $spouseOrder);

        return back();
    }

    /**
     * Set parent for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setParent(Request $request, User $user)
    {
        $this->authorize('edit', $user);
        $this->abortIfUserOutsideTenant($user);
        $parentId = $request->get('set_parent_id');

        if ($parentId) {
            $couple = Couple::findOrFail($parentId);
            $this->abortIfCoupleOutsideTenant($couple);
            $this->parentCoupleResolver->assignCouple($user, $couple);
        } else {
            $user->parent_id = null;
            $user->save();
        }

        return redirect()->route('users.show', $user);
    }

    private function abortIfUserOutsideTenant(?User $user): void
    {
        if (!$user || !$this->familyScopeResolver->hasActiveScope()) {
            return;
        }

        if (!$this->familyScopeResolver->isVisibleUser($user)) {
            abort(404);
        }
    }

    private function abortIfCoupleOutsideTenant(?Couple $couple): void
    {
        if (!$couple || !$this->familyScopeResolver->hasActiveScope()) {
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
