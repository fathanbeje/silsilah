<?php

namespace Tests\Feature;

use App\Couple;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncBaniSalamSheetCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_updates_existing_users_and_syncs_family_relations_from_bani_salam_sheet()
    {
        $masdjidi = factory(User::class)->states('male')->create([
            'name' => 'MASDJIDI ABDUL SALAM',
            'nickname' => 'MASDJIDI',
        ]);
        $nurAhadah = factory(User::class)->states('female')->create([
            'name' => 'NUR AHADAH',
            'nickname' => 'AHADAH',
        ]);
        $fathan = factory(User::class)->states('male')->create([
            'name' => 'FATHAN FAHMI',
            'nickname' => 'AAN',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'bani-salam-command-');
        file_put_contents($path, json_encode([
            [
                'Nama Lengkap' => 'MASDJIDI ABDUL SALAM',
                'Status' => 'Hidup',
                'Hubungan dengan Mbah Salam' => 'Anak',
                'Nama Panggilan' => 'MASDJIDI',
                'Kota kelahiran' => 'MALANG',
                'Tahun Lahir' => '1948',
                'Nama Ayah (lengkap)' => 'ABDUL SALAM',
                'Nama Ibu (lengkap)' => 'TASLIMAH',
                'Nama lengkap Istri / Suami' => 'NUR AHADAH',
                'Nama anak (lengkap)' => "1. FATHAN FAHMI",
                'Alamat tinggal sekarang' => 'SINGOSARI',
            ],
            [
                'Nama Lengkap' => 'NUR AHADAH',
                'Status' => 'Hidup',
                'Hubungan dengan Mbah Salam' => 'Menantu',
                'Nama Panggilan' => 'AHADAH',
                'Kota kelahiran' => 'MALANG',
                'Tahun Lahir' => '1964',
                'Nama Ayah (lengkap)' => 'ADNAN',
                'Nama Ibu (lengkap)' => 'MUNAFI\'AH',
                'Nama lengkap Istri / Suami' => 'MASDJIDI ABDUL SALAM',
                'Nama anak (lengkap)' => "1. FATHAN FAHMI",
                'Alamat tinggal sekarang' => 'SINGOSARI',
            ],
            [
                'Nama Lengkap' => 'FATHAN FAHMI',
                'Status' => 'Hidup',
                'Hubungan dengan Mbah Salam' => 'Cucu',
                'Nama Panggilan' => 'AAN',
                'Kota kelahiran' => 'MALANG',
                'Tahun Lahir' => '1987',
                'Nama Ayah (lengkap)' => 'MASDJIDI ABDUL SALAM',
                'Nama Ibu (lengkap)' => 'NUR AHADAH',
                'Nama lengkap Istri / Suami' => '',
                'Nama anak (lengkap)' => '',
                'Alamat tinggal sekarang' => 'SINGOSARI',
            ],
        ], JSON_UNESCAPED_UNICODE));

        $exitCode = $this->artisan('sheet:sync-bani-salam', [
            'path' => $path,
            '--apply' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $masdjidi->refresh();
        $nurAhadah->refresh();
        $fathan->refresh();

        $this->assertSame('1948', (string) $masdjidi->yob);
        $this->assertSame('1964', (string) $nurAhadah->yob);
        $this->assertSame('1987', (string) $fathan->yob);
        $this->assertSame('SINGOSARI', $masdjidi->address);
        $this->assertSame($masdjidi->id, $fathan->father_id);
        $this->assertSame($nurAhadah->id, $fathan->mother_id);
        $this->assertNotNull($fathan->parent_id);

        $this->seeInDatabase('couples', [
            'id' => $fathan->parent_id,
            'husband_id' => $masdjidi->id,
            'wife_id' => $nurAhadah->id,
        ]);

        @unlink($path);
    }

    /** @test */
    public function it_can_create_missing_related_spouses_from_sheet_when_gender_is_clear()
    {
        $maryam = factory(User::class)->states('female')->create([
            'name' => 'MARYAM',
            'nickname' => 'MARYAM',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'bani-salam-command-');
        file_put_contents($path, json_encode([
            [
                'Nama Lengkap' => 'MARYAM',
                'Status' => 'Hidup',
                'Hubungan dengan Mbah Salam' => 'Anak',
                'Nama Panggilan' => 'MARYAM',
                'Kota kelahiran' => 'MALANG',
                'Tahun Lahir' => '1962',
                'Nama Ayah (lengkap)' => 'ABDUL SALAM',
                'Nama Ibu (lengkap)' => 'TASLIMAH',
                'Nama lengkap Istri / Suami' => '1. H. Basuni 2. Jemiyo',
                'Nama anak (lengkap)' => '',
                'Alamat tinggal sekarang' => 'SINGOSARI',
            ],
        ], JSON_UNESCAPED_UNICODE));

        $exitCode = $this->artisan('sheet:sync-bani-salam', [
            'path' => $path,
            '--apply' => true,
            '--create-missing' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertNotNull(User::where('name', 'BASUNI')->first());
        $this->assertNotNull(User::where('name', 'JEMIYO')->first());
        $this->assertCount(2, Couple::where('wife_id', $maryam->id)->get());

        @unlink($path);
    }

    /** @test */
    public function it_does_not_reuse_an_existing_exact_name_from_another_family_branch_without_context_match()
    {
        $jufri = factory(User::class)->states('male')->create([
            'name' => 'JUFRI UMAR',
            'nickname' => 'JUFRI',
        ]);
        $syarifah = factory(User::class)->states('female')->create([
            'name' => 'SYARIFAH MUNAWWIROH',
            'nickname' => 'SYARIFAH',
        ]);
        $existingNafiah = factory(User::class)->states('female')->create([
            'name' => 'NAFIAH',
            'nickname' => 'NAFIAH',
            'father_id' => $jufri->id,
            'mother_id' => $syarifah->id,
        ]);

        $ghufronFather = factory(User::class)->states('male')->create([
            'name' => 'ABDUL SALAM',
            'nickname' => 'ABDUL SALAM',
        ]);
        $ghufronMother = factory(User::class)->states('female')->create([
            'name' => 'TASLIMAH',
            'nickname' => 'TASLIMAH',
        ]);
        $ghufron = factory(User::class)->states('male')->create([
            'name' => 'M. GHUFRON',
            'nickname' => 'GHUFRON',
            'father_id' => $ghufronFather->id,
            'mother_id' => $ghufronMother->id,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'bani-salam-command-');
        file_put_contents($path, json_encode([
            [
                'Nama Lengkap' => 'M. GHUFRON',
                'Status' => 'Hidup',
                'Hubungan dengan Mbah Salam' => 'Cucu',
                'Nama Panggilan' => 'GHUFRON',
                'Kota kelahiran' => 'MALANG',
                'Tahun Lahir' => '1980',
                'Nama Ayah (lengkap)' => 'ABDUL SALAM',
                'Nama Ibu (lengkap)' => 'TASLIMAH',
                'Nama lengkap Istri / Suami' => 'NAFIAH',
                'Nama anak (lengkap)' => '',
                'Alamat tinggal sekarang' => 'SINGOSARI',
            ],
            [
                'Nama Lengkap' => 'NAFIAH',
                'Status' => 'Hidup',
                'Hubungan dengan Mbah Salam' => 'Menantu',
                'Nama Panggilan' => 'NAFIAH',
                'Kota kelahiran' => 'MALANG',
                'Tahun Lahir' => '1982',
                'Nama Ayah (lengkap)' => '',
                'Nama Ibu (lengkap)' => '',
                'Nama lengkap Istri / Suami' => 'M. GHUFRON',
                'Nama anak (lengkap)' => '',
                'Alamat tinggal sekarang' => 'SINGOSARI',
            ],
        ], JSON_UNESCAPED_UNICODE));

        $exitCode = $this->artisan('sheet:sync-bani-salam', [
            'path' => $path,
            '--apply' => true,
            '--create-missing' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(2, User::where('name', 'NAFIAH')->count());

        $existingNafiah->refresh();
        $this->assertSame($jufri->id, $existingNafiah->father_id);
        $this->assertSame($syarifah->id, $existingNafiah->mother_id);
        $this->assertCount(0, Couple::where('wife_id', $existingNafiah->id)->get());

        $newNafiah = User::where('name', 'NAFIAH')
            ->where('id', '!=', $existingNafiah->id)
            ->first();

        $this->assertNotNull($newNafiah);
        $this->seeInDatabase('couples', [
            'husband_id' => $ghufron->id,
            'wife_id' => $newNafiah->id,
        ]);

        @unlink($path);
    }
}
