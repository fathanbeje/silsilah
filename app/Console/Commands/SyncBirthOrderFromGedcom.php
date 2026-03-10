<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncBirthOrderFromGedcom extends Command
{
    protected $signature = 'gedcom:sync-birth-order
        {file : Path ke file GEDCOM}
        {--dry-run : Tampilkan hasil tanpa menyimpan perubahan}';

    protected $description = 'Sinkronkan birth_order berdasarkan urutan CHIL pada file GEDCOM';

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (!is_file($filePath)) {
            $this->error('File GEDCOM tidak ditemukan: '.$filePath);

            return self::FAILURE;
        }

        $parsed = $this->parseGedcom($filePath);
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $unchanged = 0;
        $missingFamilies = 0;
        $missingChildren = 0;
        $ambiguousChildren = 0;
        $processedFamilies = 0;

        foreach ($parsed['families'] as $family) {
            $father = $this->findParent($family['husband_name'], 1);
            $mother = $this->findParent($family['wife_name'], 2);

            if (!$father && !$mother) {
                $missingFamilies++;
                $this->warn('Family tidak ditemukan: '
                    .($family['husband_name'] ?: '?').' + '.($family['wife_name'] ?: '?'));
                continue;
            }

            $processedFamilies++;

            foreach ($family['children'] as $index => $childName) {
                $birthOrder = $index + 1;
                $matches = $this->findChildren(
                    $childName,
                    $family['husband_name'],
                    $family['wife_name'],
                    $father?->id,
                    $mother?->id
                );

                if ($matches->isEmpty()) {
                    $missingChildren++;
                    $this->warn('Child tidak ditemukan: '.$childName.' ['.$birthOrder.']');
                    continue;
                }

                if ($matches->count() > 1) {
                    $ambiguousChildren++;
                    $this->warn('Child ambigu: '.$childName.' ['.$birthOrder.'] cocok '.$matches->count().' record');
                    continue;
                }

                /** @var \App\User $child */
                $child = $matches->first();

                if ((int) $child->birth_order === $birthOrder) {
                    $unchanged++;
                    continue;
                }

                $this->line(sprintf(
                    '%s | %s -> %d',
                    $child->name,
                    $child->birth_order ?: 'NULL',
                    $birthOrder
                ));

                if (!$dryRun) {
                    $child->birth_order = $birthOrder;
                    $child->save();
                }

                $updated++;
            }
        }

        $this->newLine();
        $this->info('Ringkasan');
        $this->line('Family diproses: '.$processedFamilies);
        $this->line('Updated: '.$updated);
        $this->line('Unchanged: '.$unchanged);
        $this->line('Family tidak ditemukan: '.$missingFamilies);
        $this->line('Child tidak ditemukan: '.$missingChildren);
        $this->line('Child ambigu: '.$ambiguousChildren);
        $this->line('Mode: '.($dryRun ? 'dry-run' : 'apply'));

        return self::SUCCESS;
    }

    private function parseGedcom(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $individuals = [];
        $families = [];
        $currentType = null;
        $currentId = null;

        foreach ($lines as $line) {
            $line = trim($line);
            $parts = explode(' ', $line, 3);
            $level = $parts[0] ?? '';
            $tag = $parts[1] ?? '';
            $value = $parts[2] ?? '';

            if ($level === '0') {
                if ($value === 'INDI') {
                    $currentType = 'INDI';
                    $currentId = trim($tag, '@');
                    $individuals[$currentId] = ['name' => null, 'gender_id' => 1];
                } elseif ($value === 'FAM') {
                    $currentType = 'FAM';
                    $currentId = trim($tag, '@');
                    $families[$currentId] = ['husband' => null, 'wife' => null, 'children' => []];
                } else {
                    $currentType = null;
                    $currentId = null;
                }

                continue;
            }

            if ($currentType === 'INDI' && $currentId) {
                if ($tag === 'NAME') {
                    $individuals[$currentId]['name'] = trim(str_replace('/', '', $value));
                } elseif ($tag === 'SEX') {
                    $individuals[$currentId]['gender_id'] = ($value === 'F' || $value === '2') ? 2 : 1;
                }
            }

            if ($currentType === 'FAM' && $currentId) {
                if ($tag === 'HUSB') {
                    $families[$currentId]['husband'] = trim($value, '@');
                } elseif ($tag === 'WIFE') {
                    $families[$currentId]['wife'] = trim($value, '@');
                } elseif ($tag === 'CHIL') {
                    $families[$currentId]['children'][] = trim($value, '@');
                }
            }
        }

        $normalizedFamilies = [];
        foreach ($families as $family) {
            $normalizedFamilies[] = [
                'husband_name' => $family['husband'] ? ($individuals[$family['husband']]['name'] ?? null) : null,
                'wife_name' => $family['wife'] ? ($individuals[$family['wife']]['name'] ?? null) : null,
                'children' => collect($family['children'])
                    ->map(fn ($childId) => $individuals[$childId]['name'] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            ];
        }

        return ['families' => $normalizedFamilies];
    }

    private function findParent(?string $name, int $genderId): ?User
    {
        if (!$name) {
            return null;
        }

        return User::query()
            ->where('gender_id', $genderId)
            ->where('name', $name)
            ->orderBy('created_at')
            ->first();
    }

    private function findChildren(
        string $name,
        ?string $fatherName,
        ?string $motherName,
        ?string $fatherId,
        ?string $motherId
    ): Collection
    {
        $matches = User::query()
            ->where('name', $name)
            ->when($fatherId, fn ($query) => $query->where('father_id', $fatherId))
            ->when($motherId, fn ($query) => $query->where('mother_id', $motherId))
            ->get();

        if ($matches->isNotEmpty()) {
            return $matches;
        }

        return User::query()
            ->where('name', $name)
            ->when($fatherName, function ($query) use ($fatherName) {
                $query->whereHas('father', fn ($parentQuery) => $parentQuery->where('name', $fatherName));
            })
            ->when($motherName, function ($query) use ($motherName) {
                $query->whereHas('mother', fn ($parentQuery) => $parentQuery->where('name', $motherName));
            })
            ->get();
    }
}
