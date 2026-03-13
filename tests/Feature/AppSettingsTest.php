<?php

namespace Tests\Feature;

use App\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_update_site_header_name()
    {
        config(['app.system_admin_emails' => 'admin@example.com']);
        $this->loginAsUser(['email' => 'admin@example.com']);

        $response = $this->scopedCall('syamsuri.bani.my.id', 'PATCH', route('app-settings.update', [], false), [
            'site_header_name' => 'Silsilah Bani Syamsuri',
        ]);

        $response->assertStatus(302);

        $this->seeInDatabase('app_settings', [
            'host' => 'syamsuri.bani.my.id',
            'key' => 'site_header_name',
            'value' => 'Silsilah Bani Syamsuri',
        ]);
    }

    /** @test */
    public function site_header_name_is_resolved_per_host_on_public_pages()
    {
        AppSetting::query()->create([
            'host' => 'syamsuri.bani.my.id',
            'key' => 'site_header_name',
            'value' => 'Silsilah Bani Syamsuri',
        ]);
        AppSetting::query()->create([
            'host' => 'salam.bani.my.id',
            'key' => 'site_header_name',
            'value' => 'Silsilah Bani Salam',
        ]);

        $syamsuriResponse = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('users.search', [], false));
        $salamResponse = $this->scopedCall('salam.bani.my.id', 'GET', route('users.search', [], false));

        $this->assertStringContainsString('Silsilah Bani Syamsuri', $syamsuriResponse->getContent());
        $this->assertStringContainsString('Silsilah Bani Salam', $salamResponse->getContent());
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
