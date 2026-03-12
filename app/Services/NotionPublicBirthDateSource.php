<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class NotionPublicBirthDateSource
{
    public function fetchRows(string $url, int $chunkSize = 100): Collection
    {
        $identifiers = $this->extractIdentifiers($url);
        $pageChunk = $this->postJson($url, 'loadCachedPageChunkV2', [
            'page' => ['id' => $identifiers['page_id']],
            'cursor' => ['stack' => []],
            'verticalColumns' => false,
        ]);

        $spaceId = data_get($pageChunk, 'spaceId')
            ?: data_get($pageChunk, "recordMap.block.{$identifiers['page_id']}.value.space_id");
        $collectionId = data_get($pageChunk, "recordMap.block.{$identifiers['page_id']}.value.collection_id");
        $viewId = $identifiers['view_id']
            ?: data_get($pageChunk, "recordMap.block.{$identifiers['page_id']}.value.view_ids.0");

        if (! $spaceId || ! $collectionId || ! $viewId) {
            throw new RuntimeException('Metadata database Notion tidak lengkap.');
        }

        $schema = collect(data_get($pageChunk, "recordMap.collection.{$collectionId}.value.schema", []));
        $propertyMap = [
            'title' => 'title',
            'gender' => $this->findPropertyIdByName($schema, 'L/P'),
            'birth_date' => $this->findPropertyIdByName($schema, 'Lahir'),
        ];

        if (! $propertyMap['gender'] || ! $propertyMap['birth_date']) {
            throw new RuntimeException('Kolom Nama Lengkap, L/P, atau Lahir tidak ditemukan di Notion.');
        }

        $blockIds = collect(data_get($pageChunk, "recordMap.collection_view.{$viewId}.value.page_sort", []))
            ->filter()
            ->values();

        if ($blockIds->isEmpty()) {
            $queryResult = $this->postJson($url, 'queryCollection?src=initial_load', [
                'collection' => [
                    'id' => $collectionId,
                    'spaceId' => $spaceId,
                ],
                'collectionView' => [
                    'id' => $viewId,
                    'spaceId' => $spaceId,
                ],
                'collectionViewBlock' => [
                    'id' => $identifiers['page_id'],
                    'spaceId' => $spaceId,
                ],
                'query' => data_get($pageChunk, "recordMap.collection_view.{$viewId}.value.query2", new \stdClass()),
                'loader' => [
                    'limit' => max(100, $chunkSize),
                    'type' => 'table',
                ],
                'clientType' => 'notion_app',
                'userTimeZone' => config('app.timezone', 'Asia/Jakarta'),
                'isFullScreen' => true,
                'isMobile' => false,
            ]);

            $blockIds = collect(data_get($queryResult, 'allBlockIds', []))
                ->filter()
                ->values();
        }

        if ($blockIds->isEmpty()) {
            return collect();
        }

        return $blockIds
            ->chunk(max(1, $chunkSize))
            ->flatMap(function (Collection $chunk) use ($url, $propertyMap) {
                return $chunk->map(function (string $blockId) use ($url, $propertyMap) {
                    $response = $this->postJson($url, 'loadCachedPageChunkV2', [
                        'page' => ['id' => $blockId],
                        'cursor' => ['stack' => []],
                        'verticalColumns' => false,
                    ]);

                    return $this->mapBlockToRow(
                        data_get($response, "recordMap.block.{$blockId}.value", []),
                        $propertyMap
                    );
                });
            })
            ->filter(function (array $row) {
                return ! empty($row['source_name']) && ! empty($row['dob']);
            })
            ->values();
    }

    public function normalizeComparableName(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/^\((?:ALM|ALMH)\.?\)\s*/iu', '', $value);
        $value = preg_replace('/^(?:ALM|ALMH)\.?\s+/iu', '', $value);
        $value = preg_replace('/^(?:(?:K\s*\.?\s*)?H(?:\s*\.?\s*)?(?:J(?:\s*\.?\s*)?)?\s+)+/iu', '', $value);
        $value = str_replace(['’', '‘', '`', '´'], "'", $value);
        $value = preg_replace("/[.'’‘`´]+/u", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return User::normalizeUppercase($value);
    }

    private function extractIdentifiers(string $url): array
    {
        $pageId = $this->extractUuidLike($url, '/([0-9a-f]{32}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i');
        if (! $pageId) {
            throw new InvalidArgumentException('Page ID Notion tidak ditemukan di URL.');
        }

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $viewId = isset($query['v']) ? $this->canonicalizeUuid($query['v']) : null;

        return [
            'page_id' => $pageId,
            'view_id' => $viewId,
        ];
    }

    private function extractUuidLike(string $value, string $pattern): ?string
    {
        if (! preg_match($pattern, $value, $matches)) {
            return null;
        }

        return $this->canonicalizeUuid($matches[1]);
    }

    private function canonicalizeUuid(string $value): string
    {
        $normalized = strtolower(str_replace('-', '', trim($value)));

        if (strlen($normalized) !== 32) {
            throw new InvalidArgumentException('ID Notion tidak valid: '.$value);
        }

        return substr($normalized, 0, 8).'-'
            .substr($normalized, 8, 4).'-'
            .substr($normalized, 12, 4).'-'
            .substr($normalized, 16, 4).'-'
            .substr($normalized, 20, 12);
    }

    protected function postJson(string $url, string $endpoint, array $payload): array
    {
        $ch = curl_init('https://www.notion.so/api/v3/'.$endpoint);
        $encodedPayload = json_encode($payload);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Origin: https://www.notion.so',
                'Referer: '.$url,
                'User-Agent: Mozilla/5.0',
            ],
            CURLOPT_POSTFIELDS => $encodedPayload,
        ]);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Request Notion gagal: '.$curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Request Notion gagal [%s]: %s',
                $statusCode,
                Str::limit($body, 300)
            ));
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Response Notion tidak valid.');
        }

        return $decoded;
    }

    private function findPropertyIdByName(Collection $schema, string $name): ?string
    {
        foreach ($schema as $propertyId => $property) {
            if (data_get($property, 'name') === $name) {
                return is_string($propertyId) ? $propertyId : null;
            }
        }

        return null;
    }

    private function mapBlockToRow(array $block, array $propertyMap): array
    {
        if (($block['type'] ?? null) !== 'page') {
            return [
                'block_id' => $block['id'] ?? null,
                'source_name' => null,
                'normalized_name' => null,
                'gender_label' => null,
                'gender_id' => null,
                'dob' => null,
                'yob' => null,
            ];
        }

        $properties = collect($block['properties'] ?? []);
        $sourceName = $this->extractPlainText($properties->get($propertyMap['title']));
        $genderLabel = $this->extractPlainText($properties->get($propertyMap['gender']));
        $dob = $this->extractDateValue($properties->get($propertyMap['birth_date']));

        return [
            'block_id' => $block['id'] ?? null,
            'source_name' => $sourceName,
            'normalized_name' => $this->normalizeComparableName($sourceName),
            'gender_label' => $genderLabel,
            'gender_id' => $this->mapGender($genderLabel),
            'dob' => $dob,
            'yob' => $dob ? substr($dob, 0, 4) : null,
        ];
    }

    private function extractPlainText($propertyValue): ?string
    {
        if (! is_array($propertyValue)) {
            return null;
        }

        $parts = collect($propertyValue)
            ->map(function ($entry) {
                return is_array($entry) ? ($entry[0] ?? null) : null;
            })
            ->filter(fn ($part) => is_string($part) && trim($part) !== '')
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        return trim($parts->implode(''));
    }

    private function extractDateValue($propertyValue): ?string
    {
        if (! is_array($propertyValue)) {
            return null;
        }

        $startDate = $this->findDateStartValue($propertyValue);

        return is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
            ? $startDate
            : null;
    }

    private function findDateStartValue($value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        if (isset($value['start_date']) && is_string($value['start_date'])) {
            return $value['start_date'];
        }

        foreach ($value as $item) {
            $found = $this->findDateStartValue($item);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function mapGender(?string $genderLabel): ?int
    {
        $normalized = User::normalizeUppercase($genderLabel);

        return match ($normalized) {
            'BIN' => 1,
            'BINTI' => 2,
            default => null,
        };
    }
}
