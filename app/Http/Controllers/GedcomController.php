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

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            $parts = explode(' ', $line, 3);
            $level = $parts[0] ?? '';
            $tag = $parts[1] ?? '';
            $value = $parts[2] ?? '';

            if ($level == '0') {
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
                if ($tag == 'NAME') {
                    // example value: John /Doe/
                    $cleanName = str_replace('/', '', $value);
                    $indis[$currentObjId]['name'] = $cleanName;
                    $nicknameParts = explode(' ', trim($cleanName));
                    $indis[$currentObjId]['nickname'] = $nicknameParts[0] ?: 'Unknown';
                } elseif ($tag == 'SEX') {
                    $indis[$currentObjId]['gender_id'] = ($value == 'F' || $value == '2') ? 2 : 1;
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
}
