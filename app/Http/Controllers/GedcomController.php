<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GedcomController extends Controller
{
    public function index()
    {
        return view('gedcom.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'gedcom' => 'required|file',
        ]);

        $file = $request->file('gedcom');
        $content = file_get_contents($file->path());
        $lines = explode("\n", $content);

        $indiMap = [];
        $famMap = [];

        $indis = [];
        $fams = [];

        $currentObj = null;
        $currentObjId = null;
        $currentEvent = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            $parts = explode(' ', $line, 3);
            $level = $parts[0] ?? '';
            $tag = $parts[1] ?? '';
            $value = $parts[2] ?? '';

            if ($level == '0') {
                $currentEvent = null;

                if ($value == 'INDI') {
                    $currentObj = 'INDI';
                    $currentObjId = trim($tag, '@');
                    $indiMap[$currentObjId] = Str::uuid()->toString();
                    $indis[$currentObjId] = [
                        'id' => $indiMap[$currentObjId],
                        'nickname' => 'Unknown',
                        'name' => null,
                        'gender_id' => 1,
                        'father_id' => null,
                        'mother_id' => null,
                        'parent_id' => null,
                        'dob' => null,
                        'yob' => null,
                        'dod' => null,
                        'yod' => null,
                        'manager_id' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } elseif ($value == 'FAM') {
                    $currentObj = 'FAM';
                    $currentObjId = trim($tag, '@');
                    $famMap[$currentObjId] = [
                        'id' => Str::uuid()->toString(),
                        'husband' => null,
                        'wife' => null,
                        'chil' => [],
                    ];
                } else {
                    $currentObj = null;
                }
            } elseif ($currentObj == 'INDI') {
                if ($level == '1') {
                    $currentEvent = null;

                    if ($tag == 'NAME') {
                        // example value: John /Doe/
                        $cleanName = str_replace('/', '', $value);
                        $indis[$currentObjId]['name'] = $cleanName;
                        $nicknameParts = explode(' ', trim($cleanName));
                        $indis[$currentObjId]['nickname'] = $nicknameParts[0] ?: 'Unknown';
                    } elseif ($tag == 'SEX') {
                        $indis[$currentObjId]['gender_id'] = ($value == 'F' || $value == '2') ? 2 : 1;
                    } elseif ($tag == 'BIRT') {
                        $currentEvent = 'BIRT';
                    } elseif ($tag == 'DEAT') {
                        $currentEvent = 'DEAT';
                    }
                } elseif ($level == '2' && $currentEvent == 'BIRT' && $tag == 'DATE') {
                    $birthData = $this->parseGedcomBirthDate($value);
                    if ($birthData) {
                        $indis[$currentObjId]['dob'] = $birthData['dob'];
                        $indis[$currentObjId]['yob'] = $birthData['yob'];
                    }
                } elseif ($level == '2' && $currentEvent == 'DEAT' && $tag == 'DATE') {
                    $deathData = $this->parseGedcomDeathDate($value);
                    if ($deathData) {
                        $indis[$currentObjId]['dod'] = $deathData['dod'];
                        $indis[$currentObjId]['yod'] = $deathData['yod'];
                    }
                }
            } elseif ($currentObj == 'FAM') {
                if ($tag == 'HUSB') {
                    $famMap[$currentObjId]['husband'] = trim($value, '@');
                } elseif ($tag == 'WIFE') {
                    $famMap[$currentObjId]['wife'] = trim($value, '@');
                } elseif ($tag == 'CHIL') {
                    $famMap[$currentObjId]['chil'][] = trim($value, '@');
                }
            }
        }

        foreach ($famMap as $famId => $fam) {
            $husbId = $fam['husband'] ? ($indiMap[$fam['husband']] ?? null) : null;
            $wifeId = $fam['wife'] ? ($indiMap[$fam['wife']] ?? null) : null;

            // Link children
            foreach ($fam['chil'] as $chilTag) {
                if (isset($indis[$chilTag])) {
                    if ($husbId) {
                        $indis[$chilTag]['father_id'] = $husbId;
                    }
                    if ($wifeId) {
                        $indis[$chilTag]['mother_id'] = $wifeId;
                    }
                }
            }
        }

        // Chunk insert INDIs
        $indiChunks = array_chunk($indis, 500);
        foreach ($indiChunks as $chunk) {
            DB::table('users')->insert($chunk);
        }

        foreach ($famMap as $famId => $fam) {
            $husbId = $fam['husband'] ? ($indiMap[$fam['husband']] ?? null) : null;
            $wifeId = $fam['wife'] ? ($indiMap[$fam['wife']] ?? null) : null;

            if ($husbId && $wifeId) {
                DB::table('couples')->insert([
                    'id' => $fam['id'],
                    'husband_id' => $husbId,
                    'wife_id' => $wifeId,
                    'manager_id' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->route('gedcom.index')->with('success', 'File GEDCOM berhasil diimpor!');
    }

    private function parseGedcomBirthDate(string $rawDate): ?array
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $rawDate)));
        $monthMap = [
            'JAN' => '01',
            'FEB' => '02',
            'MAR' => '03',
            'APR' => '04',
            'MAY' => '05',
            'JUN' => '06',
            'JUL' => '07',
            'AUG' => '08',
            'SEP' => '09',
            'OCT' => '10',
            'NOV' => '11',
            'DEC' => '12',
        ];

        if (preg_match('/^(\d{1,2}) ([A-Z]{3}) (\d{4})$/', $normalized, $matches)) {
            $month = $monthMap[$matches[2]] ?? null;
            if (!$month) {
                return null;
            }

            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];

            return [
                'dob' => $year.'-'.$month.'-'.$day,
                'yob' => $this->normalizeYearForColumn($year),
            ];
        }

        if (preg_match('/^\d{4}$/', $normalized)) {
            return [
                'dob' => null,
                'yob' => $this->normalizeYearForColumn($normalized),
            ];
        }

        if (preg_match('/(\d{4})$/', $normalized, $matches)) {
            return [
                'dob' => null,
                'yob' => $this->normalizeYearForColumn($matches[1]),
            ];
        }

        return null;
    }

    private function parseGedcomDeathDate(string $rawDate): ?array
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $rawDate)));

        if (preg_match('/^(BEF|AFT|ABT|EST|CAL|BET|FROM|TO)\b/', $normalized)) {
            return [
                'dod' => null,
                'yod' => null,
            ];
        }

        $monthMap = [
            'JAN' => '01',
            'FEB' => '02',
            'MAR' => '03',
            'APR' => '04',
            'MAY' => '05',
            'JUN' => '06',
            'JUL' => '07',
            'AUG' => '08',
            'SEP' => '09',
            'OCT' => '10',
            'NOV' => '11',
            'DEC' => '12',
        ];

        if (preg_match('/^(\d{1,2}) ([A-Z]{3}) (\d{4})$/', $normalized, $matches)) {
            $month = $monthMap[$matches[2]] ?? null;
            if (!$month) {
                return null;
            }

            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];

            return [
                'dod' => $year.'-'.$month.'-'.$day,
                'yod' => $this->normalizeYearForColumn($year),
            ];
        }

        if (preg_match('/^\d{4}$/', $normalized)) {
            return [
                'dod' => null,
                'yod' => $this->normalizeYearForColumn($normalized),
            ];
        }

        return [
            'dod' => null,
            'yod' => null,
        ];
    }

    private function normalizeYearForColumn(string $year): ?string
    {
        $yearInt = (int) $year;

        if ($yearInt < 1901 || $yearInt > 2155) {
            return null;
        }

        return (string) $yearInt;
    }
}
