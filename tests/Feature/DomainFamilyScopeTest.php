<?php

namespace Tests\Feature;

use App\Couple;
use App\DomainFamilyScope;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainFamilyScopeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_search_only_returns_members_inside_current_host_scope()
    {
        [$core, $descendant, $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();

        $this->useScopedHost('syamsuri.bani.my.id');
        $this->visit('/profile-search?q=scope');

        $this->seePageIs('http://syamsuri.bani.my.id/profile-search?q=scope');
        $this->seeText($core->name);
        $this->seeText($descendant->name);
        $this->seeText($descendantSpouse->name);
        $this->dontSeeText($spouseParent->name);
    }

    /** @test */
    public function guest_autocomplete_only_returns_visible_scope_members()
    {
        [$core, $descendant, $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', '/profile-search/autocomplete', ['q' => 'scope'], [
            'HTTP_HOST' => 'syamsuri.bani.my.id',
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $names = collect($payload)->pluck('name')->all();
        $corePayload = collect($payload)->firstWhere('id', $core->id);

        $this->assertContains($core->display_name, $names);
        $this->assertContains($descendant->display_name, $names);
        $this->assertContains($descendantSpouse->display_name, $names);
        $this->assertNotContains($spouseParent->display_name, $names);
        $this->assertSame(route('users.chart', $core, false), $corePayload['chart_url']);
        $this->assertSame(route('users.show', $core, false), $corePayload['profile_url']);
    }

    /** @test */
    public function scoped_landing_page_uses_scope_root_for_examples_and_tree_cta()
    {
        [$core, $descendant, $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', '/profile-search');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Contoh: '.$core->display_name, $response->getContent());
        $this->assertStringContainsString($descendant->display_name, $response->getContent());
        $this->assertStringContainsString(route('users.tree', $core, false), $response->getContent());
        $this->assertStringNotContainsString($spouseParent->display_name, $response->getContent());
    }

    /** @test */
    public function guest_cannot_open_chart_for_spouse_parent_outside_scope()
    {
        [, , , $spouseParent] = $this->createScopedFamilyGraph();

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('users.chart', $spouseParent, false));

        $this->assertSame(404, $response->getStatusCode());
    }

    /** @test */
    public function admin_can_bypass_scope_for_direct_profile_access()
    {
        [, , , $spouseParent] = $this->createScopedFamilyGraph();
        config(['app.system_admin_emails' => 'admin@example.net']);
        $this->loginAsUser(['email' => 'admin@example.net']);

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('users.show', $spouseParent, false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($spouseParent->name, $response->getContent());
    }

    /** @test */
    public function guest_chart_still_hides_relatives_outside_scope()
    {
        [, $descendant, $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('users.chart', $descendantSpouse, false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString($spouseParent->name, $response->getContent());
        $this->assertStringContainsString($descendant->name, $response->getContent());
    }

    /** @test */
    public function admin_can_bypass_scope_inside_chart_relations()
    {
        [, , $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();
        config(['app.system_admin_emails' => 'admin@example.net']);
        $this->loginAsUser(['email' => 'admin@example.net']);

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('users.chart', $descendantSpouse, false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($spouseParent->name, $response->getContent());
    }

    /** @test */
    public function admin_landing_search_results_remain_scoped_to_current_host()
    {
        [$core, $descendant, $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();
        config(['app.system_admin_emails' => 'admin@example.net']);
        $this->loginAsUser(['email' => 'admin@example.net']);

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', '/profile-search', ['q' => 'scope']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($core->display_name, $response->getContent());
        $this->assertStringContainsString($descendant->display_name, $response->getContent());
        $this->assertStringContainsString($descendantSpouse->display_name, $response->getContent());
        $this->assertStringNotContainsString($spouseParent->display_name, $response->getContent());
    }

    /** @test */
    public function admin_landing_autocomplete_remains_scoped_to_current_host()
    {
        [$core, $descendant, $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();
        config(['app.system_admin_emails' => 'admin@example.net']);
        $this->loginAsUser(['email' => 'admin@example.net']);

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', '/profile-search/autocomplete', ['q' => 'scope']);

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $names = collect($payload)->pluck('name')->all();

        $this->assertContains($core->display_name, $names);
        $this->assertContains($descendant->display_name, $names);
        $this->assertContains($descendantSpouse->display_name, $names);
        $this->assertNotContains($spouseParent->display_name, $names);
    }

    /** @test */
    public function request_without_registered_host_renders_empty_public_landing()
    {
        [$core, $descendant, $descendantSpouse, $spouseParent] = $this->createScopedFamilyGraph();

        $response = $this->scopedCall('unscoped.bani.my.id', 'GET', '/profile-search', ['q' => 'scope']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString($core->display_name, $response->getContent());
        $this->assertStringNotContainsString($descendant->display_name, $response->getContent());
        $this->assertStringNotContainsString($descendantSpouse->display_name, $response->getContent());
        $this->assertStringNotContainsString($spouseParent->display_name, $response->getContent());
        $this->assertStringNotContainsString('Hasil Pencarian', $response->getContent());
    }

    /** @test */
    public function request_without_registered_host_returns_empty_public_autocomplete_results()
    {
        $this->createScopedFamilyGraph();

        $response = $this->scopedCall('unscoped.bani.my.id', 'GET', '/profile-search/autocomplete', ['q' => 'scope']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    /** @test */
    public function request_without_registered_host_cannot_open_public_family_routes()
    {
        [$core] = $this->createScopedFamilyGraph();
        $core->update(['dob' => '1980-01-02', 'email' => null]);

        $chartResponse = $this->scopedCall('unscoped.bani.my.id', 'GET', route('users.chart', $core, false));
        $treeResponse = $this->scopedCall('unscoped.bani.my.id', 'GET', route('users.tree', $core, false));
        $claimResponse = $this->scopedCall('unscoped.bani.my.id', 'POST', route('claim-registration.store', $core, false), [
            'email' => 'pemohon@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'dob' => '1980-01-02',
        ]);
        $registrationRequestResponse = $this->scopedCall('unscoped.bani.my.id', 'POST', route('registration-requests.store', $core, false), [
            'request_email' => 'pemohon@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
        $editRequestCreateResponse = $this->scopedCall('unscoped.bani.my.id', 'GET', route('user-edit-requests.create', $core, false));
        $editRequestStoreResponse = $this->scopedCall('unscoped.bani.my.id', 'POST', route('user-edit-requests.store', $core, false), [
            'requester_name' => 'Pemohon',
            'requester_whatsapp' => '08123',
            'nickname' => $core->nickname,
            'gender_id' => (string) $core->gender_id,
        ]);

        $this->assertSame(404, $chartResponse->getStatusCode());
        $this->assertSame(404, $treeResponse->getStatusCode());
        $this->assertSame(404, $claimResponse->getStatusCode());
        $this->assertSame(404, $registrationRequestResponse->getStatusCode());
        $this->assertSame(404, $editRequestCreateResponse->getStatusCode());
        $this->assertSame(404, $editRequestStoreResponse->getStatusCode());
    }

    /** @test */
    public function localhost_without_registered_scope_still_allows_public_access()
    {
        [$core, $descendant] = $this->createScopedFamilyGraph();

        $searchResponse = $this->scopedCall('localhost', 'GET', '/profile-search', ['q' => 'scope']);
        $treeResponse = $this->scopedCall('localhost', 'GET', route('users.tree', $core, false));

        $this->assertSame(200, $searchResponse->getStatusCode());
        $this->assertStringContainsString($core->display_name, $searchResponse->getContent());
        $this->assertStringContainsString($descendant->display_name, $searchResponse->getContent());
        $this->assertSame(200, $treeResponse->getStatusCode());
        $this->assertStringContainsString($core->display_name, $treeResponse->getContent());
    }

    /** @test */
    public function scoped_admin_only_sees_current_host_in_domain_scope_management()
    {
        [$core] = $this->createScopedFamilyGraph();
        config(['app.system_admin_emails' => 'admin@example.net']);
        $this->loginAsUser(['email' => 'admin@example.net']);

        DomainFamilyScope::create([
            'host' => 'salam.bani.my.id',
            'core_user_id' => $core->id,
            'is_active' => true,
        ]);

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('domain-family-scopes.index', [], false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('syamsuri.bani.my.id', $response->getContent());
        $this->assertStringNotContainsString('salam.bani.my.id', $response->getContent());
    }

    private function createScopedFamilyGraph(): array
    {
        $core = factory(User::class)->states('male')->create([
            'name' => 'CORE SCOPE',
            'nickname' => 'CORE SCOPE',
        ]);
        $descendantSpouse = factory(User::class)->states('female')->create([
            'name' => 'MENANTU SCOPE',
            'nickname' => 'MENANTU SCOPE',
        ]);
        $descendant = factory(User::class)->states('male')->create([
            'name' => 'ANAK SCOPE',
            'nickname' => 'ANAK SCOPE',
            'father_id' => $core->id,
        ]);
        $spouseParent = factory(User::class)->states('male')->create([
            'name' => 'ORANG TUA LUAR SCOPE',
            'nickname' => 'ORANG TUA LUAR SCOPE',
            'manager_id' => $core->id,
        ]);

        $marriage = factory(Couple::class)->create([
            'husband_id' => $descendant->id,
            'wife_id' => $descendantSpouse->id,
            'manager_id' => $core->id,
        ]);

        $descendantSpouse->update(['father_id' => $spouseParent->id]);
        DomainFamilyScope::create([
            'host' => 'syamsuri.bani.my.id',
            'core_user_id' => $core->id,
            'is_active' => true,
        ]);

        return [$core, $descendant, $descendantSpouse, $spouseParent];
    }

    private function scopedCall(string $host, string $method, string $uri, array $parameters = [], array $server = [])
    {
        $this->useScopedHost($host);

        return $this->call($method, $uri, $parameters, [], [], array_merge([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ], $server));
    }

    private function useScopedHost(string $host): void
    {
        $this->baseUrl = 'http://'.$host;
        config(['app.url' => 'http://'.$host]);
        url()->forceRootUrl('http://'.$host);
    }
}
