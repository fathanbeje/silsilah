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

        $this->patch(route('app-settings.update'), [
            'site_header_name' => 'Silsilah Bani Syamsuri',
        ]);

        $this->seeInDatabase('app_settings', [
            'key' => 'site_header_name',
            'value' => 'Silsilah Bani Syamsuri',
        ]);
    }

    /** @test */
    public function updated_site_header_name_is_visible_on_public_pages()
    {
        AppSetting::query()->create([
            'key' => 'site_header_name',
            'value' => 'Silsilah Bani Syamsuri',
        ]);

        $this->visit(route('users.search'));
        $this->see('Silsilah Bani Syamsuri');
    }
}
