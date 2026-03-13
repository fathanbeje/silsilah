<?php

namespace Tests\Feature;

use App\DomainFamilyScope;
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

    /** @test */
    public function scoped_admin_only_sees_birth_order_families_inside_current_tenant()
    {
        config(['app.system_admin_emails' => 'admin@mail.com']);
        $this->loginAsUser(['email' => 'admin@mail.com']);

        $scopeRoot = factory(User::class)->states('male')->create(['name' => 'ROOT SALAM']);
        $scopeMother = factory(User::class)->states('female')->create(['name' => 'IBU SALAM']);
        $scopeChild = factory(User::class)->create([
            'name' => 'ANAK SALAM',
            'father_id' => $scopeRoot->id,
            'mother_id' => $scopeMother->id,
            'birth_order' => null,
        ]);

        $outsideFather = factory(User::class)->states('male')->create(['name' => 'AYAH LUAR']);
        $outsideMother = factory(User::class)->states('female')->create(['name' => 'IBU LUAR']);
        $outsideChild = factory(User::class)->create([
            'name' => 'ANAK LUAR',
            'father_id' => $outsideFather->id,
            'mother_id' => $outsideMother->id,
            'birth_order' => null,
        ]);

        DomainFamilyScope::create([
            'host' => 'salam.bani.my.id',
            'core_user_id' => $scopeRoot->id,
            'is_active' => true,
        ]);

        $response = $this->scopedCall('salam.bani.my.id', 'GET', route('birth-orders.index', [], false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($scopeChild->name, $response->getContent());
        $this->assertStringNotContainsString($outsideChild->name, $response->getContent());
    }

    /** @test */
    public function scoped_admin_cannot_update_birth_order_for_family_outside_current_tenant()
    {
        config(['app.system_admin_emails' => 'admin@mail.com']);
        $this->loginAsUser(['email' => 'admin@mail.com']);

        $scopeRoot = factory(User::class)->states('male')->create(['name' => 'ROOT SALAM']);
        $outsideFather = factory(User::class)->states('male')->create(['name' => 'AYAH LUAR']);
        $outsideMother = factory(User::class)->states('female')->create(['name' => 'IBU LUAR']);
        $outsideChild = factory(User::class)->create([
            'name' => 'ANAK LUAR',
            'father_id' => $outsideFather->id,
            'mother_id' => $outsideMother->id,
            'birth_order' => null,
        ]);

        DomainFamilyScope::create([
            'host' => 'salam.bani.my.id',
            'core_user_id' => $scopeRoot->id,
            'is_active' => true,
        ]);

        $familyKey = implode('|', [$outsideFather->id, $outsideMother->id, 'null']);

        $response = $this->scopedCall('salam.bani.my.id', 'POST', route('birth-orders.update', [], false), [
            'family_key' => $familyKey,
            'children' => [
                $outsideChild->id => 1,
            ],
        ]);

        $response->assertStatus(302);
        $this->seeInDatabase('users', [
            'id' => $outsideChild->id,
            'birth_order' => null,
        ]);
    }

    private function scopedCall(string $host, string $method, string $uri, array $parameters = [], array $server = [])
    {
        $this->baseUrl = 'http://'.$host;
        config(['app.url' => 'http://'.$host]);
        url()->forceRootUrl('http://'.$host);

        return $this->call($method, $uri, $parameters, [], [], array_merge([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ], $server));
    }
}
