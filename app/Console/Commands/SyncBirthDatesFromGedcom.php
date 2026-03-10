<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncBirthDatesFromGedcom extends Command
{
    protected $signature = 'gedcom:sync-birth-dates
        {file : Path ke file GEDCOM}
        {--dry-run : Tampilkan hasil tanpa menyimpan perubahan}';

    protected $description = 'Sinkronkan tanggal lahir dan tahun lahir dari file GEDCOM';

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
        $unparseable = 0;

        foreach ($individuals as $individual) {
            if (!$individual['name'] || !$individual['birth_raw']) {
                continue;
            }

            $birthData = $this->parseBirthDate($individual['birth_raw']);
            if (!$birthData) {
                $unparseable++;
                $this->warn('Tanggal tidak dikenali: '.$individual['name'].' ['.$individual['birth_raw'].']');
                continue;
            }

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
            $currentDob = $user->dob;
            $currentYob = $user->yob;
            $newDob = $birthData['dob'];
            $newYob = $birthData['yob'];

            if ($currentDob === $newDob && (string) $currentYob === (string) $newYob) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '%s | dob %s -> %s | yob %s -> %s',
                $user->name,
                $currentDob ?: 'NULL',
                $newDob ?: 'NULL',
                $currentYob ?: 'NULL',
                $newYob ?: 'NULL'
            ));

            if (!$dryRun) {
                $user->dob = $newDob;
                $user->yob = $newYob;
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
        $this->line('Tanggal tidak dikenali: '.$unparseable);
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
                        'birth_raw' => null,
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
                } elseif ($tag === 'BIRT') {
                    $currentEvent = 'BIRT';
                }

                continue;
            }

            if ($level === '2' && $currentEvent === 'BIRT' && $tag === 'DATE') {
                $individuals[$currentId]['birth_raw'] = trim($value);
            }
        }

        return collect($individuals)->values();
    }

    private function parseBirthDate(string $rawDate): ?array
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

    private function normalizeYearForColumn(string $year): ?string
    {
        $yearInt = (int) $year;

        if ($yearInt < 1901 || $yearInt > 2155) {
            return null;
        }

        return (string) $yearInt;
    }
}
