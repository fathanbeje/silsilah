<?php

namespace Tests\Feature;

use App\DomainFamilyScope;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BirthdayScopeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function non_admin_cannot_access_birthday_page()
    {
        $this->loginAsUser(['email' => 'member@example.com']);

        $response = $this->get(route('birthdays.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function scoped_admin_only_sees_birthdays_inside_current_tenant()
    {
        config(['app.system_admin_emails' => 'admin@mail.com']);
        $this->loginAsUser(['email' => 'admin@mail.com']);

        $scopeRoot = factory(User::class)->states('male')->create(['name' => 'ROOT SALAM']);
        $inScope = factory(User::class)->create([
            'name' => 'ULTAH SALAM',
            'father_id' => $scopeRoot->id,
            'dob' => now()->addDays(10)->format('Y-m-d'),
            'is_deceased' => false,
        ]);
        $outScope = factory(User::class)->create([
            'name' => 'ULTAH LUAR',
            'dob' => now()->addDays(10)->format('Y-m-d'),
            'is_deceased' => false,
        ]);

        DomainFamilyScope::create([
            'host' => 'salam.bani.my.id',
            'core_user_id' => $scopeRoot->id,
            'is_active' => true,
        ]);

        $response = $this->scopedCall('salam.bani.my.id', 'GET', route('birthdays.index', [], false));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($inScope->display_name, $response->getContent());
        $this->assertStringNotContainsString($outScope->display_name, $response->getContent());
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
