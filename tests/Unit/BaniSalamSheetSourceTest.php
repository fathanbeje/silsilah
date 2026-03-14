<?php

namespace Tests\Unit;

use App\Services\BaniSalamSheetSource;
use Tests\TestCase;

class BaniSalamSheetSourceTest extends TestCase
{
    /** @test */
    public function it_normalizes_bani_salam_rows_and_splits_multiple_relations()
    {
        $path = tempnam(sys_get_temp_dir(), 'bani-salam-');

        file_put_contents($path, json_encode([
            [
                'Nama Lengkap' => 'Alm. H. BISRI',
                'Status' => 'Almarhum',
                'Hubungan dengan Mbah Salam' => 'Menantu',
                'Nama Panggilan' => 'H.BISRI',
                'Kota kelahiran' => 'PASURUAN',
                'Tahun Lahir' => '1943',
                'Nama Ayah (lengkap)' => 'H. MUSTOFA',
                'Nama Ibu (lengkap)' => 'HJ. KHASANAH',
                'Nama lengkap Istri / Suami' => 'KHUMILAH',
                'Nama anak (lengkap)' => "1. ABDULLOH (ALM)\n2. KHOIRUN NISA",
                'Alamat tinggal sekarang' => 'NONGKOJAJAR',
            ],
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

        $rows = app(BaniSalamSheetSource::class)->loadRows($path)->keyBy('normalized_name');

        $bisri = $rows->get('BISRI');
        $this->assertTrue($bisri['is_deceased']);
        $this->assertSame(1, $bisri['gender_id']);
        $this->assertCount(1, $bisri['spouses']);
        $this->assertCount(2, $bisri['children']);
        $this->assertTrue($bisri['children'][0]['is_deceased']);
        $this->assertSame('ABDULLOH', $bisri['children'][0]['name']);

        $maryam = $rows->get('MARYAM');
        $this->assertSame(2, $maryam['gender_id']);
        $this->assertSame('opposite_of_honorific_spouse', $maryam['gender_reason']);
        $this->assertCount(2, $maryam['spouses']);
        $this->assertSame('BASUNI', $maryam['spouses'][0]['name']);
        $this->assertSame(1, $maryam['spouses'][0]['order']);
        $this->assertSame('JEMIYO', $maryam['spouses'][1]['name']);

        @unlink($path);
    }
}
