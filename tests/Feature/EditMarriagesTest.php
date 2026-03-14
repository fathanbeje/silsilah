<?php

namespace Tests\Feature;

use App\Couple;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditMarriagesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_visit_a_marriage_detail_page()
    {
        $user = $this->loginAsUser();
        $couple = factory(Couple::class)->create();

        $this->visit(route('couples.show', $couple));

        $this->see($couple->husband->name);
        $this->see($couple->wife->name);
    }

    /** @test */
    public function manager_can_edit_couple_data()
    {
        $user = $this->loginAsUser();
        $couple = factory(Couple::class)->create(['manager_id' => $user->id]);

        $this->visit(route('couples.show', $couple));

        $this->click(trans('couple.edit'));
        $this->seePageIs(route('couples.edit', $couple));

        $this->submitForm(trans('couple.update'), [
            'spouse_order' => 2,
            'marriage_date' => '2010-04-04',
            'divorce_date' => '2035-04-04',
        ]);

        $this->seePageIs(route('couples.show', $couple));

        $this->seeInDatabase('couples', [
            'id' => $couple->id,
            'spouse_order' => 2,
            'marriage_date' => '2010-04-04',
            'divorce_date' => '2035-04-04',
        ]);
    }

    /** @test */
    public function manager_can_delete_a_childless_couple()
    {
        $manager = $this->loginAsUser();
        $couple = factory(Couple::class)->create(['manager_id' => $manager->id]);

        $this->delete(route('couples.destroy', $couple));

        $this->dontSeeInDatabase('couples', [
            'id' => $couple->id,
        ]);
    }

    /** @test */
    public function manager_cannot_delete_a_couple_that_is_still_used_as_parent()
    {
        $manager = $this->loginAsUser();
        $couple = factory(Couple::class)->create(['manager_id' => $manager->id]);
        factory(User::class)->create([
            'father_id' => $couple->husband_id,
            'mother_id' => $couple->wife_id,
            'parent_id' => $couple->id,
            'manager_id' => $manager->id,
        ]);

        $this->delete(route('couples.destroy', $couple));

        $this->seeInDatabase('couples', [
            'id' => $couple->id,
        ]);
    }
}
