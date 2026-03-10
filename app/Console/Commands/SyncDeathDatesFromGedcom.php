<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncDeathDatesFromGedcom extends Command
{
    protected $signature = 'gedcom:sync-death-dates
        {file : Path ke file GEDCOM}
        {--dry-run : Tampilkan hasil tanpa menyimpan perubahan}';

    protected $description = 'Sinkronkan tanggal wafat dan tahun wafat dari file GEDCOM';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (!is_file($filePath)) {
            $this->error('File GEDCOM tidak ditemukan: '.$filePath);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $individuals = $this->parseGedcom($filePath);

        $updated = 0;
        $unchanged = 0;
        $missing = 0;
        $ambiguous = 0;

        foreach ($individuals as $individual) {
            if (!$individual['name'] || !$individual['death_raw']) {
                continue;
            }

            $deathData = $this->parseDeathDate($individual['death_raw']);
            $matches = User::query()
                ->where('name', $individual['name'])
                ->where('gender_id', $individual['gender_id'])
                ->get();

            if ($matches->isEmpty()) {
                $missing++;
                $this->warn('User tidak ditemukan: '.$individual['name']);
                continue;
            }

            if ($matches->count() > 1) {
                $ambiguous++;
                $this->warn('User ambigu: '.$individual['name'].' cocok '.$matches->count().' record');
                continue;
            }

            /** @var \App\User $user */
            $user = $matches->first();
            $currentDod = $user->dod;
            $currentYod = $user->yod;
            $newDod = $deathData['dod'];
            $newYod = $deathData['yod'];

            if ($currentDod === $newDod && (string) $currentYod === (string) $newYod) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '%s | dod %s -> %s | yod %s -> %s',
                $user->name,
                $currentDod ?: 'NULL',
                $newDod ?: 'NULL',
                $currentYod ?: 'NULL',
                $newYod ?: 'NULL'
            ));

            if (!$dryRun) {
                $user->dod = $newDod;
                $user->yod = $newYod;
                $user->save();
            }

            $updated++;
        }

        $this->newLine();
        $this->info('Ringkasan');
        $this->line('Updated: '.$updated);
        $this->line('Unchanged: '.$unchanged);
        $this->line('User tidak ditemukan: '.$missing);
        $this->line('User ambigu: '.$ambiguous);
        $this->line('Mode: '.($dryRun ? 'dry-run' : 'apply'));

        return self::SUCCESS;
    }

    private function parseGedcom(string $filePath): Collection
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $individuals = [];
        $currentType = null;
        $currentId = null;
        $currentEvent = null;

        foreach ($lines as $line) {
            $line = trim($line);
            $parts = explode(' ', $line, 3);
            $level = $parts[0] ?? '';
            $tag = $parts[1] ?? '';
            $value = $parts[2] ?? '';

            if ($level === '0') {
                $currentEvent = null;

                if ($value === 'INDI') {
                    $currentType = 'INDI';
                    $currentId = trim($tag, '@');
                    $individuals[$currentId] = [
                        'name' => null,
                        'gender_id' => 1,
                        'death_raw' => null,
                    ];
                } else {
                    $currentType = null;
                    $currentId = null;
                }

                continue;
            }

            if ($currentType !== 'INDI' || !$currentId) {
                continue;
            }

            if ($level === '1') {
                $currentEvent = null;

                if ($tag === 'NAME') {
                    $individuals[$currentId]['name'] = trim(str_replace('/', '', $value));
                } elseif ($tag === 'SEX') {
                    $individuals[$currentId]['gender_id'] = ($value === 'F' || $value === '2') ? 2 : 1;
                } elseif ($tag === 'DEAT') {
                    $currentEvent = 'DEAT';
                }

                continue;
            }

            if ($level === '2' && $currentEvent === 'DEAT' && $tag === 'DATE') {
                $individuals[$currentId]['death_raw'] = trim($value);
            }
        }

        return collect($individuals)->values();
    }

    private function parseDeathDate(string $rawDate): array
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $rawDate)));

        if (preg_match('/^(BEF|AFT|ABT|EST|CAL|BET|FROM|TO)\b/', $normalized)) {
            return ['dod' => null, 'yod' => null];
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
                return ['dod' => null, 'yod' => null];
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

        return ['dod' => null, 'yod' => null];
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
