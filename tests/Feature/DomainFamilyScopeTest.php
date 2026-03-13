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
    public function request_without_registered_host_falls_back_to_global_visibility()
    {
        [, , , $spouseParent] = $this->createScopedFamilyGraph();

        $response = $this->scopedCall('unscoped.bani.my.id', 'GET', '/profile-search', ['q' => 'luar']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($spouseParent->name, $response->getContent());
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
