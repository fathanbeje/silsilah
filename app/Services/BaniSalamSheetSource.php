<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Collection;
use RuntimeException;

class BaniSalamSheetSource
{
    public function loadRows(string $path): Collection
    {
        $rawRows = $this->readRows($path);

        $rows = collect($rawRows)
            ->map(fn (array $row) => $this->mapRawRow($row))
            ->filter(fn (array $row) => ! empty($row['normalized_name']))
            ->values();

        return $this->inferRowGenders($rows);
    }

    public function sanitizeImportedName(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/^\d+\.\s*/u', '', $value);
        $value = preg_replace('/^\((?:ALM|ALMH)\.?\)\s*/iu', '', $value);
        $value = preg_replace('/^(?:ALM|ALMH)\.?\s+/iu', '', $value);
        $value = preg_replace('/\((?:ALM|ALMH)\.?\)/iu', '', $value);
        $value = preg_replace('/^(?:(?:K\s*\.?\s*)?H(?:\s*\.?\s*)?(?:J(?:\s*\.?\s*)?)?\s+)+/iu', '', $value);
        $value = str_replace(['’', '‘', '`', '´'], "'", $value);
        $value = preg_replace("/[.'’‘`´]+/u", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return User::normalizeUppercase(trim($value));
    }

    public function normalizeComparableName(?string $value): ?string
    {
        $sanitized = $this->sanitizeImportedName($value);
        if (! $sanitized) {
            return null;
        }

        $variants = [
            $sanitized,
            preg_replace('/\bM\b/u', 'MUHAMMAD', $sanitized),
            preg_replace('/\bABD\b/u', 'ABDUL', $sanitized),
            preg_replace('/\bMOH\b/u', 'MUHAMMAD', $sanitized),
            str_replace('MASJIDI', 'MASDJIDI', $sanitized),
            preg_replace('/\bAS\b/u', 'ABDUL SALAM', $sanitized),
            preg_replace('/\bADS(?:\s+SALAM)?\b/u', 'ABDUL SALAM', $sanitized),
        ];

        return collect($variants)
            ->filter()
            ->map(fn ($item) => preg_replace('/\s+/', ' ', trim((string) $item)))
            ->filter()
            ->first();
    }

    public function comparableNameVariants(?string $value): Collection
    {
        $normalized = $this->normalizeComparableName($value);
        if (! $normalized) {
            return collect();
        }

        $variants = collect([$normalized]);
        $variants->push(str_replace(' ', '', $normalized));

        $expanded = collect([
            preg_replace('/\bM\b/u', 'MUHAMMAD', $normalized),
            preg_replace('/\bABD\b/u', 'ABDUL', $normalized),
            preg_replace('/\bMOH\b/u', 'MUHAMMAD', $normalized),
            str_replace('MASJIDI', 'MASDJIDI', $normalized),
            preg_replace('/\bAS\b/u', 'ABDUL SALAM', $normalized),
            preg_replace('/\bADS(?:\s+SALAM)?\b/u', 'ABDUL SALAM', $normalized),
        ])->filter();

        foreach ($expanded as $variant) {
            $variants->push($variant);
            $variants->push(str_replace(' ', '', $variant));
        }

        return $variants->map(fn ($variant) => preg_replace('/\s+/', ' ', trim((string) $variant)))
            ->filter()
            ->unique()
            ->values();
    }

    public function inferDeceasedState(?string $name, ?string $status = null): bool
    {
        $name = is_string($name) ? trim($name) : '';
        $status = is_string($status) ? trim($status) : '';

        if ($status !== '' && preg_match('/ALMARHUM|WAFAT/iu', $status)) {
            return true;
        }

        return (bool) preg_match('/(?:^|\s|\()(ALM|ALMH)\.?(?:\s|\)|$)/iu', $name);
    }

    private function readRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('File sheet Bani Salam tidak ditemukan: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Format JSON sheet Bani Salam tidak valid.');
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function mapRawRow(array $row): array
    {
        $sourceName = trim((string) ($row['Nama Lengkap'] ?? ''));
        $name = $this->sanitizeImportedName($sourceName);
        $normalizedName = $this->normalizeComparableName($sourceName);
        $status = trim((string) ($row['Status'] ?? ''));
        $genderLabel = trim((string) ($row['GENDER'] ?? ''));
        $genderId = $this->mapGenderLabel($genderLabel) ?? $this->inferGenderFromName($sourceName);

        $father = $this->mapNamedEntity($row['Nama Ayah (lengkap)'] ?? null, 'father');
        $mother = $this->mapNamedEntity($row['Nama Ibu (lengkap)'] ?? null, 'mother');
        $spouses = $this->splitNameEntries($row['Nama lengkap Istri / Suami'] ?? null)
            ->values()
            ->map(fn (array $entry) => $this->mapNamedEntity($entry['source_name'], null, $entry['order']))
            ->filter()
            ->values();
        $children = $this->splitNameEntries($row['Nama anak (lengkap)'] ?? null)
            ->values()
            ->map(fn (array $entry) => $this->mapNamedEntity($entry['source_name'], 'child', $entry['order']))
            ->filter()
            ->values();

        return [
            'source_name' => $sourceName,
            'name' => $name,
            'normalized_name' => $normalizedName,
            'name_aliases' => $this->comparableNameVariants($sourceName)->all(),
            'nickname' => $this->sanitizeImportedName($row['Nama Panggilan'] ?? null),
            'relationship_label' => trim((string) ($row['Hubungan dengan Mbah Salam'] ?? '')),
            'status_label' => $status,
            'is_deceased' => $this->inferDeceasedState($sourceName, $status),
            'gender_id' => $genderId,
            'gender_reason' => $this->mapGenderLabel($genderLabel) ? 'sheet_gender' : ($genderId ? 'honorific' : null),
            'city' => User::normalizeUppercase($row['Kota kelahiran'] ?? null),
            'yob' => $this->normalizeYear($row['Tahun Lahir'] ?? null),
            'address' => User::normalizeUppercase($row['Alamat tinggal sekarang'] ?? null),
            'photo_url' => trim((string) ($row['photoid'] ?? $row['Foto setengah Badan'] ?? '')),
            'profile_doc_url' => trim((string) ($row['Merged Doc URL - ProfilBaniSalam'] ?? $row['Link to merged Doc - ProfilBaniSalam'] ?? '')),
            'parents' => collect([$father, $mother])->filter()->values()->all(),
            'parent_aliases' => collect([$father, $mother])->filter()->flatMap(fn (array $item) => $item['name_aliases'])->unique()->values()->all(),
            'spouses' => $spouses->all(),
            'spouse_aliases' => $spouses->flatMap(fn (array $item) => $item['name_aliases'])->unique()->values()->all(),
            'children' => $children->all(),
            'child_aliases' => $children->flatMap(fn (array $item) => $item['name_aliases'])->unique()->values()->all(),
        ];
    }

    private function mapNamedEntity(?string $sourceName, ?string $role = null, ?int $order = null): ?array
    {
        if (! is_string($sourceName) || trim($sourceName) === '') {
            return null;
        }

        $sourceName = trim($sourceName);
        $genderId = match ($role) {
            'father' => 1,
            'mother' => 2,
            default => $this->inferGenderFromName($sourceName),
        };

        return [
            'source_name' => $sourceName,
            'name' => $this->sanitizeImportedName($sourceName),
            'normalized_name' => $this->normalizeComparableName($sourceName),
            'name_aliases' => $this->comparableNameVariants($sourceName)->all(),
            'gender_id' => $genderId,
            'role' => $role,
            'order' => $order,
            'is_deceased' => $this->inferDeceasedState($sourceName),
        ];
    }

    private function splitNameEntries(?string $value): Collection
    {
        if (! is_string($value) || trim($value) === '') {
            return collect();
        }

        $value = preg_replace("/\r\n|\r/u", "\n", trim($value));
        $segments = [];

        if (preg_match('/\d+\.\s*/u', $value)) {
            $parts = preg_split('/(?=(?:^|\n|\s)\d+\.\s*)/u', $value, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $part = trim((string) $part);
                $part = preg_replace('/^\d+\.\s*/u', '', $part);
                if ($part !== '') {
                    $segments[] = $part;
                }
            }
        } else {
            $parts = preg_split('/\n|,(?=\s*[A-Z])|\s+dan\s+/iu', $value, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part !== '') {
                    $segments[] = $part;
                }
            }
        }

        return collect($segments)
            ->values()
            ->map(fn (string $segment, int $index) => [
                'source_name' => $segment,
                'order' => $index + 1,
            ]);
    }

    private function inferGenderFromName(?string $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/(?:^|\s|\()(ALMH)\.?(?:\s|\)|$)/iu', $value)) {
            return 2;
        }

        if (preg_match('/(?:^|\s|\()(ALM)\.?(?:\s|\)|$)/iu', $value)) {
            return 1;
        }

        if (preg_match('/^(?:HJ|NYAI|UMMI|BUNDA|IBU)\.?\s+/iu', $value)) {
            return 2;
        }

        if (preg_match('/^(?:(?:K\s*\.?\s*)?H(?:\s*\.?\s*)?(?:J(?:\s*\.?\s*)?)?)\s+/iu', $value)) {
            return 1;
        }

        if (preg_match('/^(?:PAK|BAPAK|MAS)\s+/iu', $value)) {
            return 1;
        }

        if (preg_match('/^(?:BU|IBU|MBAK)\s+/iu', $value)) {
            return 2;
        }

        return null;
    }

    private function mapGenderLabel(?string $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return match (User::normalizeUppercase($value)) {
            'L', 'LAKI-LAKI', 'LAKI LAKI', 'MALE' => 1,
            'P', 'PEREMPUAN', 'FEMALE' => 2,
            default => null,
        };
    }

    private function normalizeYear($value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        if (preg_match('/\b(18|19|20)\d{2}\b/', (string) $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function inferRowGenders(Collection $rows): Collection
    {
        $rowsArray = $rows->values()->all();

        for ($i = 0; $i < 6; $i++) {
            $changed = false;
            $rowsByName = collect($rowsArray)->keyBy('normalized_name');

            foreach ($rowsArray as $index => $row) {
                if (! empty($row['gender_id'])) {
                    continue;
                }

                $resolvedGender = null;
                $reason = null;

                foreach ($rowsArray as $other) {
                    foreach ($other['parents'] as $parent) {
                        if (($parent['normalized_name'] ?? null) !== $row['normalized_name']) {
                            continue;
                        }

                        if (! empty($parent['gender_id'])) {
                            $resolvedGender = (int) $parent['gender_id'];
                            $reason = 'referenced_as_'.$parent['role'];
                            break 2;
                        }
                    }
                }

                if (! $resolvedGender) {
                    foreach ($row['spouses'] as $spouse) {
                        $spouseRow = $rowsByName->get($spouse['normalized_name'] ?? '');
                        if ($spouseRow && ! empty($spouseRow['gender_id'])) {
                            $resolvedGender = (int) $spouseRow['gender_id'] === 1 ? 2 : 1;
                            $reason = 'opposite_of_explicit_spouse';
                            break;
                        }

                        if (! empty($spouse['gender_id'])) {
                            $resolvedGender = (int) $spouse['gender_id'] === 1 ? 2 : 1;
                            $reason = 'opposite_of_honorific_spouse';
                            break;
                        }
                    }
                }

                if (! $resolvedGender) {
                    foreach ($rowsArray as $other) {
                        foreach ($other['spouses'] as $spouse) {
                            if (($spouse['normalized_name'] ?? null) !== $row['normalized_name']) {
                                continue;
                            }

                            if (! empty($other['gender_id'])) {
                                $resolvedGender = (int) $other['gender_id'] === 1 ? 2 : 1;
                                $reason = 'referenced_as_spouse';
                                break 2;
                            }
                        }
                    }
                }

                if ($resolvedGender) {
                    $rowsArray[$index]['gender_id'] = $resolvedGender;
                    $rowsArray[$index]['gender_reason'] = $reason;
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }
        }

        return collect($rowsArray)->values();
    }
}
