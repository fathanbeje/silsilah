<?php

namespace Tests\Feature;

use App\Couple;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageUserFamiliesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_update_their_father()
    {
        $user = $this->loginAsUser();
        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->dontSeeElement('input', ['name' => 'set_father']);
        $this->click(trans('user.set_father'));
        $this->seePageIs(route('users.show', [$user->id, 'action' => 'set_father']));
        $this->seeElement('input', ['name' => 'set_father']);

        $this->submitForm('set_father_button', [
            'set_father' => 'Nama Ayah',
        ]);

        $this->seeInDatabase('users', [
            'nickname' => 'NAMA AYAH',
        ]);

        $this->assertEquals('NAMA AYAH', $user->fresh()->father->nickname);
    }

    /** @test */
    public function user_can_update_their_mother()
    {
        $user = $this->loginAsUser();
        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->dontSeeElement('input', ['name' => 'set_mother']);
        $this->click(trans('user.set_mother'));
        $this->seePageIs(route('users.show', [$user->id, 'action' => 'set_mother']));
        $this->seeElement('input', ['name' => 'set_mother']);

        $this->submitForm('set_mother_button', [
            'set_mother' => 'Nama Ibu',
        ]);

        $this->seeInDatabase('users', [
            'nickname'   => 'NAMA IBU',
            'manager_id' => $user->id,
        ]);

        $this->assertEquals('NAMA IBU', $user->fresh()->mother->nickname);
    }

    /** @test */
    public function user_can_add_childrens()
    {
        $user = $this->loginAsUser(['gender_id' => 1]);
        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->click(trans('user.add_child'));
        $this->seeElement('input', ['name' => 'add_child_name']);
        $this->seeElement('input', ['name' => 'add_child_gender_id']);
        $this->seeElement('select', ['name' => 'add_child_parent_id']);

        $this->submitForm(trans('user.add_child'), [
            'add_child_name'      => 'Nama Anak 1',
            'add_child_gender_id' => 1,
            'add_child_parent_id' => '',
        ]);

        $this->seeInDatabase('users', [
            'nickname'   => 'NAMA ANAK 1',
            'gender_id'  => 1,
            'father_id'  => $user->id,
            'mother_id'  => null,
            'parent_id'  => null,
            'manager_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_can_add_childrens_with_parent_id_if_exist()
    {
        $husband = $this->loginAsUser(['gender_id' => 1]);
        $wife = factory(User::class)->states('female')->create(['manager_id' => $husband->id]);
        $husband->addWife($wife);

        $marriageId = $husband->fresh()->wifes->first()->pivot->id;

        $this->post(route('family-actions.add-child', $husband), [
            'add_child_name'      => 'Nama Anak 1',
            'add_child_gender_id' => 1,
            'add_child_parent_id' => $marriageId,
        ]);

        $this->seeInDatabase('users', [
            'nickname'   => 'NAMA ANAK 1',
            'gender_id'  => 1,
            'father_id'  => $husband->id,
            'mother_id'  => $wife->id,
            'manager_id' => $husband->id,
        ]);
    }

    /** @test */
    public function user_can_add_children_with_birth_order()
    {
        $user = $this->loginAsUser(['gender_id' => 1]);
        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->click(trans('user.add_child'));
        $this->seeElement('input', ['name' => 'add_child_birth_order']);

        $this->submitForm(trans('user.add_child'), [
            'add_child_name'        => 'Nama Anak 1',
            'add_child_gender_id'   => 1,
            'add_child_birth_order' => 2,
            'add_child_parent_id'   => '',
        ]);

        $this->seeInDatabase('users', [
            'nickname'    => 'NAMA ANAK 1',
            'gender_id'   => 1,
            'father_id'   => $user->id,
            'mother_id'   => null,
            'parent_id'   => null,
            'manager_id'  => $user->id,
            'birth_order' => 2,
        ]);
    }

    /** @test */
    public function user_can_set_wife()
    {
        $user = $this->loginAsUser(['gender_id' => 1]);
        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->click(trans('user.add_wife'));
        $this->seeElement('input', ['name' => 'set_wife']);

        $this->submitForm('set_wife_button', [
            'set_wife'      => 'Nama Istri',
            'marriage_date' => '2010-01-01',
        ]);

        $this->seeInDatabase('users', [
            'nickname'  => 'NAMA ISTRI',
            'gender_id' => 2,
        ]);

        $wife = User::where([
            'nickname'  => 'NAMA ISTRI',
            'gender_id' => 2,
        ])->first();

        $this->seeInDatabase('couples', [
            'husband_id'    => $user->id,
            'wife_id'       => $wife->id,
            'marriage_date' => '2010-01-01',
            'spouse_order'  => 1,
            'manager_id'    => $user->id,
        ]);
    }

    /** @test */
    public function user_can_set_husband()
    {
        $user = $this->loginAsUser(['gender_id' => 2]);
        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->click(trans('user.add_husband'));
        $this->seeElement('input', ['name' => 'set_husband']);

        $this->submitForm('set_husband_button', [
            'set_husband'   => 'Nama Suami',
            'marriage_date' => '2010-03-03',
        ]);

        $this->seeInDatabase('users', [
            'nickname'   => 'NAMA SUAMI',
            'gender_id'  => 1,
            'manager_id' => $user->id,
        ]);

        $husband = User::where([
            'nickname'  => 'NAMA SUAMI',
            'gender_id' => 1,
        ])->first();

        $this->seeInDatabase('couples', [
            'husband_id'    => $husband->id,
            'wife_id'       => $user->id,
            'marriage_date' => '2010-03-03',
            'spouse_order'  => 1,
            'manager_id'    => $user->id,
        ]);
    }

    /** @test */
    public function user_can_pick_father_from_existing_user()
    {
        $user = $this->loginAsUser();
        $father = factory(User::class)->states('male')->create();

        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->dontSeeElement('input', ['name' => 'set_father']);
        $this->click(trans('user.set_father'));
        $this->seePageIs(route('users.show', [$user->id, 'action' => 'set_father']));
        $this->seeElement('input', ['name' => 'set_father']);
        $this->seeElement('select', ['name' => 'set_father_id']);

        $this->submitForm('set_father_button', [
            'set_father'    => '',
            'set_father_id' => $father->id,
        ]);

        $this->assertEquals($father->nickname, $user->fresh()->father->nickname);
    }

    /** @test */
    public function user_can_pick_mother_from_existing_user()
    {
        $user = $this->loginAsUser();
        $mother = factory(User::class)->states('female')->create();

        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->dontSeeElement('input', ['name' => 'set_mother']);
        $this->click(trans('user.set_mother'));
        $this->seePageIs(route('users.show', [$user->id, 'action' => 'set_mother']));
        $this->seeElement('input', ['name' => 'set_mother']);
        $this->seeElement('select', ['name' => 'set_mother_id']);

        $this->submitForm('set_mother_button', [
            'set_mother'    => '',
            'set_mother_id' => $mother->id,
        ]);

        $this->assertEquals($mother->nickname, $user->fresh()->mother->nickname);
    }

    /** @test */
    public function user_can_pick_wife_from_existing_user()
    {
        $user = $this->loginAsUser(['gender_id' => 1]);
        $wife = factory(User::class)->states('female')->create();

        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->click(trans('user.add_wife'));
        $this->seeElement('input', ['name' => 'set_wife']);
        $this->seeElement('select', ['name' => 'set_wife_id']);

        $this->submitForm('set_wife_button', [
            'set_wife'      => '',
            'set_wife_id'   => $wife->id,
            'marriage_date' => '2010-01-01',
        ]);

        $this->seeInDatabase('couples', [
            'husband_id'    => $user->id,
            'wife_id'       => $wife->id,
            'marriage_date' => '2010-01-01',
            'spouse_order'  => 1,
            'manager_id'    => $user->id,
        ]);
    }

    /** @test */
    public function user_can_pick_husband_from_existing_user()
    {
        $user = $this->loginAsUser(['gender_id' => 2]);
        $husband = factory(User::class)->states('male')->create();

        $this->visit(route('profile'));
        $this->seePageIs(route('profile'));
        $this->click(trans('user.add_husband'));
        $this->seeElement('input', ['name' => 'set_husband']);
        $this->seeElement('select', ['name' => 'set_husband_id']);

        $this->submitForm('set_husband_button', [
            'set_husband'    => '',
            'set_husband_id' => $husband->id,
            'marriage_date'  => '2010-03-03',
        ]);

        $this->seeInDatabase('couples', [
            'husband_id'    => $husband->id,
            'wife_id'       => $user->id,
            'marriage_date' => '2010-03-03',
            'spouse_order'  => 1,
            'manager_id'    => $user->id,
        ]);
    }

    /** @test */
    public function user_can_set_manual_spouse_order_when_adding_a_wife()
    {
        $user = $this->loginAsUser(['gender_id' => 1]);

        $this->post(route('family-actions.add-wife', $user), [
            'set_wife' => 'Nama Istri',
            'spouse_order' => 2,
        ]);

        $wife = User::where([
            'nickname'  => 'NAMA ISTRI',
            'gender_id' => 2,
        ])->first();

        $this->seeInDatabase('couples', [
            'husband_id' => $user->id,
            'wife_id' => $wife->id,
            'spouse_order' => 2,
        ]);
    }

    /** @test */
    public function user_can_set_parent_from_existing_couple_id()
    {
        $user = $this->loginAsUser();
        $husband = factory(User::class)->states('male')->create();
        $wife = factory(User::class)->states('female')->create();
        $husband->addWife($wife);

        $marriageId = $husband->fresh()->wifes->first()->pivot->id;

        $this->visit(route('users.show', [$user->id, 'action' => 'set_parent']));
        $this->seeElement('select', ['name' => 'set_parent_id']);

        $this->submitForm('set_parent_button', [
            'set_parent_id' => $marriageId,
        ]);

        $this->seeInDatabase('users', [
            'id'         => $user->id,
            'father_id'  => $husband->id,
            'mother_id'  => $wife->id,
            'parent_id'  => $marriageId,
            'manager_id' => $user->id,
        ]);
    }

    /** @test */
    public function setting_father_then_mother_auto_creates_parent_couple_for_the_user()
    {
        $user = $this->loginAsUser();
        $father = factory(User::class)->states('male')->create();
        $mother = factory(User::class)->states('female')->create();

        $this->visit(route('profile'));
        $this->click(trans('user.set_father'));
        $this->submitForm('set_father_button', [
            'set_father' => '',
            'set_father_id' => $father->id,
        ]);

        $this->assertNull($user->fresh()->parent_id);

        $this->visit(route('profile'));
        $this->click(trans('user.set_mother'));
        $this->submitForm('set_mother_button', [
            'set_mother' => '',
            'set_mother_id' => $mother->id,
        ]);

        $user->refresh();
        $this->assertNotNull($user->parent_id);
        $this->seeInDatabase('couples', [
            'id' => $user->parent_id,
            'husband_id' => $father->id,
            'wife_id' => $mother->id,
        ]);
    }

    /** @test */
    public function setting_both_parents_reuses_existing_couple_instead_of_creating_a_duplicate()
    {
        $user = $this->loginAsUser();
        $father = factory(User::class)->states('male')->create();
        $mother = factory(User::class)->states('female')->create();
        $couple = factory(Couple::class)->create([
            'husband_id' => $father->id,
            'wife_id' => $mother->id,
            'manager_id' => $user->id,
        ]);

        $this->visit(route('profile'));
        $this->click(trans('user.set_father'));
        $this->submitForm('set_father_button', [
            'set_father' => '',
            'set_father_id' => $father->id,
        ]);

        $this->visit(route('profile'));
        $this->click(trans('user.set_mother'));
        $this->submitForm('set_mother_button', [
            'set_mother' => '',
            'set_mother_id' => $mother->id,
        ]);

        $this->assertSame($couple->id, $user->fresh()->parent_id);
        $this->assertEquals(1, Couple::where('husband_id', $father->id)->where('wife_id', $mother->id)->count());
    }

    /** @test */
    public function mother_profile_lists_children_loaded_via_parent_couple()
    {
        $mother = $this->loginAsUser(['gender_id' => 2, 'name' => 'NUR AHADAH', 'nickname' => 'NUR']);
        $father = factory(User::class)->states('male')->create(['manager_id' => $mother->id]);
        $mother->addHusband($father);

        $coupleId = $mother->fresh()->husbands->first()->pivot->id;
        $child = factory(User::class)->create([
            'name' => 'ANAK NUR',
            'nickname' => 'ANAK NUR',
            'parent_id' => $coupleId,
            'father_id' => null,
            'mother_id' => null,
        ]);

        $this->visit(route('users.show', $mother))
            ->see('ANAK NUR')
            ->see(__('user.childs').' (1)');

        $this->assertEquals($child->id, $mother->fresh()->childs->first()->id);
    }
}
