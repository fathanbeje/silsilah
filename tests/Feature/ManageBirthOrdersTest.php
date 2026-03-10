<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageBirthOrdersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_birth_order_management_page()
    {
        config(['app.system_admin_emails' => 'admin@mail.com']);
        $admin = $this->loginAsUser(['email' => 'admin@mail.com']);
        $father = factory(User::class)->states('male')->create();
        $mother = factory(User::class)->states('female')->create();
        factory(User::class)->create([
            'name' => 'Child One',
            'father_id' => $father->id,
            'mother_id' => $mother->id,
            'birth_order' => null,
        ]);

        $this->visit(route('birth-orders.index'));
        $this->seePageIs(route('birth-orders.index'));
        $this->see($father->name);
        $this->see($mother->name);
        $this->see('Child One');
        $this->see($admin->name);
    }

    /** @test */
    public function admin_can_update_birth_order_for_a_family()
    {
        config(['app.system_admin_emails' => 'admin@mail.com']);
        $this->loginAsUser(['email' => 'admin@mail.com']);
        $father = factory(User::class)->states('male')->create(['name' => 'Father']);
        $mother = factory(User::class)->states('female')->create(['name' => 'Mother']);
        $childA = factory(User::class)->create([
            'name' => 'Child A',
            'father_id' => $father->id,
            'mother_id' => $mother->id,
            'birth_order' => null,
        ]);
        $childB = factory(User::class)->create([
            'name' => 'Child B',
            'father_id' => $father->id,
            'mother_id' => $mother->id,
            'birth_order' => null,
        ]);

        $familyKey = implode('|', [$father->id, $mother->id, 'null']);

        $this->post(route('birth-orders.update'), [
            'family_key' => $familyKey,
            'children' => [
                $childA->id => 2,
                $childB->id => 1,
            ],
        ]);

        $this->seeInDatabase('users', [
            'id' => $childA->id,
            'birth_order' => 2,
        ]);

        $this->seeInDatabase('users', [
            'id' => $childB->id,
            'birth_order' => 1,
        ]);
    }
}
