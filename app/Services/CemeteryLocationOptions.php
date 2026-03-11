<?php

namespace App\Services;

use App\User;
use App\UserMetadata;
use Illuminate\Support\Collection;

class CemeteryLocationOptions
{
    public function all(): Collection
    {
        $rows = UserMetadata::query()
            ->whereIn('key', User::METADATA_KEYS)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->get(['user_id', 'key', 'value'])
            ->groupBy('user_id')
            ->map(function (Collection $items) {
                $values = [
                    'name' => null,
                    'address' => null,
                    'latitude' => null,
                    'longitude' => null,
                ];

                foreach ($items as $item) {
                    if ($item->key === 'cemetery_location_name') {
                        $values['name'] = trim((string) $item->value) ?: null;
                    }
                    if ($item->key === 'cemetery_location_address') {
                        $values['address'] = trim((string) $item->value) ?: null;
                    }
                    if ($item->key === 'cemetery_location_latitude') {
                        $values['latitude'] = trim((string) $item->value) ?: null;
                    }
                    if ($item->key === 'cemetery_location_longitude') {
                        $values['longitude'] = trim((string) $item->value) ?: null;
                    }
                }

                return $values;
            })
            ->filter(function (array $item) {
                return ! empty(array_filter($item));
            });

        return $rows
            ->unique(function (array $item) {
                return json_encode([
                    $item['name'],
                    $item['address'],
                    $item['latitude'],
                    $item['longitude'],
                ]);
            })
            ->map(function (array $item, $index) {
                $labelParts = array_filter([
                    $item['name'],
                    $item['address'],
                ]);

                return [
                    'id' => 'cemetery_'.($index + 1),
                    'label' => implode(' - ', $labelParts) ?: 'Lokasi makam tanpa nama',
                    'name' => $item['name'],
                    'address' => $item['address'],
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                ];
            })
            ->sortBy('label')
            ->values();
    }
}
