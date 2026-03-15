<?php

namespace Tests\Feature;

use App\Couple;
use App\DomainFamilyScope;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class DeathIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function scoped_host_can_open_death_index_grouped_by_generation()
    {
        Carbon::setTestNow('2026-03-15 09:00:00');
        [$core, $rootSpouse, $child, $childSpouse, $grandchild, $outsider] = $this->createDeathScopeGraph();

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('deaths.index', [], false));
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Database Wafat '.$core->display_name, $content);
        $this->assertStringContainsString('Semua Wafat', $content);
        $this->assertStringContainsString('Haul Bulan Ini', $content);
        $this->assertStringContainsString('1. Anak', $content);
        $this->assertStringContainsString('2. Cucu', $content);
        $this->assertStringContainsString($child->display_name, $content);
        $this->assertStringContainsString($childSpouse->display_name, $content);
        $this->assertStringContainsString($grandchild->display_name, $content);
        $this->assertStringNotContainsString('data-death-row="'.$outsider->id.'"', $content);
        $this->assertStringNotContainsString('data-death-row="'.$rootSpouse->id.'"', $content);
        $this->assertStringContainsString('Kandung', $content);
        $this->assertStringContainsString('Menantu', $content);
        $this->assertStringContainsString('2024-03-12', $content);
        $this->assertStringContainsString('3 Ramadan 1445 H', $content);
        $this->assertStringContainsString('Tidak tersedia', $content);
        $this->assertMatchesRegularExpression('/1\. Anak[\s\S]*ANAK WAFAT RAMADAN[\s\S]*MANTU WAFAT RAMADAN/', $content);
        $this->assertMatchesRegularExpression('/2\. Cucu[\s\S]*CUCU WAFAT TAHUN/', $content);
    }

    /** @test */
    public function scoped_host_haul_tab_only_shows_current_hijri_month_rows()
    {
        Carbon::setTestNow('2026-03-15 09:00:00');
        [, , $child, $childSpouse, $grandchild] = $this->createDeathScopeGraph();

        $response = $this->scopedCall('syamsuri.bani.my.id', 'GET', route('deaths.index', ['tab' => 'haul-bulan-ini'], false));
        $content = $response->getContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Haul Bulan Ini', $content);
        $this->assertStringContainsString('Ramadan 1447 H', $content);
        $this->assertStringContainsString($child->display_name, $content);
        $this->assertStringContainsString($childSpouse->display_name, $content);
        $this->assertStringNotContainsString($grandchild->display_name, $content);
        $this->assertStringNotContainsString('Tidak tersedia', $content);
        $this->assertStringContainsString('3 Ramadan 1445 H', $content);
        $this->assertStringContainsString('13 Ramadan 1445 H', $content);
        $this->assertStringContainsString('hari yang lalu', $content);
        $this->assertStringNotContainsString('330 hari lagi', $content);
    }

    /** @test */
    public function request_without_registered_scope_cannot_open_public_death_index()
    {
        Carbon::setTestNow('2026-03-15 09:00:00');
        $this->createDeathScopeGraph();

        $response = $this->scopedCall('unscoped.bani.my.id', 'GET', route('deaths.index', [], false));

        $this->assertSame(404, $response->getStatusCode());
    }

    private function createDeathScopeGraph(): array
    {
        $core = factory(User::class)->states('male')->create([
            'name' => 'CORE WAFAT',
            'nickname' => 'CORE WAFAT',
        ]);
        $rootSpouse = factory(User::class)->states('female')->create([
            'name' => 'PASANGAN CORE WAFAT',
            'nickname' => 'PASANGAN CORE WAFAT',
            'is_deceased' => true,
            'dod' => '2019-06-21',
            'manager_id' => $core->id,
        ]);
        $rootMarriage = factory(Couple::class)->create([
            'husband_id' => $core->id,
            'wife_id' => $rootSpouse->id,
            'manager_id' => $core->id,
        ]);

        $child = factory(User::class)->states('male')->create([
            'name' => 'ANAK WAFAT RAMADAN',
            'nickname' => 'ANAK WAFAT RAMADAN',
            'father_id' => $core->id,
            'mother_id' => $rootSpouse->id,
            'parent_id' => $rootMarriage->id,
            'manager_id' => $core->id,
            'is_deceased' => true,
            'dod' => '2024-03-12',
        ]);
        $childSpouse = factory(User::class)->states('female')->create([
            'name' => 'MANTU WAFAT RAMADAN',
            'nickname' => 'MANTU WAFAT RAMADAN',
            'manager_id' => $core->id,
            'is_deceased' => true,
            'dod' => '2024-03-22',
        ]);
        $childMarriage = factory(Couple::class)->create([
            'husband_id' => $child->id,
            'wife_id' => $childSpouse->id,
            'manager_id' => $core->id,
        ]);

        $grandchild = factory(User::class)->states('male')->create([
            'name' => 'CUCU WAFAT TAHUN',
            'nickname' => 'CUCU WAFAT TAHUN',
            'father_id' => $child->id,
            'mother_id' => $childSpouse->id,
            'parent_id' => $childMarriage->id,
            'manager_id' => $core->id,
            'is_deceased' => true,
            'yod' => '2016',
        ]);
        $outsider = factory(User::class)->states('male')->create([
            'name' => 'LUAR SCOPE WAFAT',
            'nickname' => 'LUAR SCOPE WAFAT',
            'manager_id' => $core->id,
            'is_deceased' => true,
            'dod' => '2024-03-12',
        ]);

        DomainFamilyScope::create([
            'host' => 'syamsuri.bani.my.id',
            'core_user_id' => $core->id,
            'is_active' => true,
        ]);

        DB::table('user_metadata')->insert([
            [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => $child->id,
                'key' => 'cemetery_location_name',
                'value' => 'Makam Anak',
            ],
            [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => $childSpouse->id,
                'key' => 'cemetery_location_name',
                'value' => 'Makam Mantu',
            ],
        ]);

        return [$core, $rootSpouse, $child, $childSpouse, $grandchild, $outsider];
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
