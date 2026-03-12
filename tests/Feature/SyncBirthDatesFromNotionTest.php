<?php

namespace Tests\Feature;

use App\Services\NotionPublicBirthDateSource;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncBirthDatesFromNotionTest extends TestCase
{
    use RefreshDatabase;

    private const NOTION_URL = 'https://www.notion.so/singosari/7ddd3b07ca20491caacd9527d7e1e0a7?v=b22e2d8186124cb9bcffc8c2303a43e5';

    /** @test */
    public function notion_birth_date_source_fetches_public_rows()
    {
        $source = new class extends NotionPublicBirthDateSource {
            protected function postJson(string $url, string $endpoint, array $payload): array
            {
                if ($endpoint === 'loadCachedPageChunkV2') {
                    $pageId = $payload['page']['id'] ?? null;

                    return match ($pageId) {
                        '7ddd3b07-ca20-491c-aacd-9527d7e1e0a7' => $this->pageChunkResponse(),
                        'row-1' => $this->singleRowResponse('row-1', '(Almh.) Hj. Munafi\'ah', 'Binti', '1936-01-01'),
                        'row-2' => $this->singleRowResponse('row-2', 'Yusrul Hana', 'Bin', '1981-09-20'),
                        default => throw new \RuntimeException('Unexpected page '.$pageId),
                    };
                }

                return match ($endpoint) {
                    'queryCollection?src=initial_load' => ['allBlockIds' => ['row-1', 'row-2']],
                    default => throw new \RuntimeException('Unexpected endpoint '.$endpoint),
                };
            }

            private function pageChunkResponse(): array
            {
                return [
                    'spaceId' => 'space-1',
                    'recordMap' => [
                        'block' => [
                            '7ddd3b07-ca20-491c-aacd-9527d7e1e0a7' => [
                                'value' => [
                                    'id' => '7ddd3b07-ca20-491c-aacd-9527d7e1e0a7',
                                    'space_id' => 'space-1',
                                    'collection_id' => 'collection-1',
                                    'view_ids' => ['b22e2d81-8612-4cb9-bcff-c8c2303a43e5'],
                                ],
                            ],
                        ],
                        'collection' => [
                            'collection-1' => [
                                'value' => [
                                    'schema' => [
                                        'title' => ['name' => 'Nama Lengkap', 'type' => 'title'],
                                        '`Ryo' => ['name' => 'L/P', 'type' => 'select'],
                                        'c{wo' => ['name' => 'Lahir', 'type' => 'date'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            private function singleRowResponse(string $rowId, string $name, string $gender, string $startDate): array
            {
                return [
                    'recordMap' => [
                        'block' => [
                            $rowId => [
                                'value' => [
                                    'id' => $rowId,
                                    'type' => 'page',
                                    'properties' => [
                                        'title' => [[$name]],
                                        '`Ryo' => [[$gender]],
                                        'c{wo' => [['‣', [['d', ['type' => 'date', 'start_date' => $startDate]]]]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        };

        $rows = $source->fetchRows(self::NOTION_URL, 50);

        $this->assertCount(2, $rows);
        $this->assertSame('MUNAFI AH', $rows[0]['normalized_name']);
        $this->assertSame(2, $rows[0]['gender_id']);
        $this->assertSame('1936-01-01', $rows[0]['dob']);
        $this->assertSame('1981-09-20', $rows[1]['dob']);
    }

    /** @test */
    public function notion_birth_date_sync_command_updates_matching_users()
    {
        $mother = factory(User::class)->states('female')->create([
            'name' => 'HJ. MUNAFI\'AH',
            'nickname' => 'MUNAFI\'AH',
            'dob' => null,
            'yob' => null,
        ]);
        $son = factory(User::class)->states('male')->create([
            'name' => 'YUSRUL HANA',
            'nickname' => 'YUSRUL',
            'dob' => null,
            'yob' => null,
        ]);

        $this->app->instance(NotionPublicBirthDateSource::class, new class extends NotionPublicBirthDateSource {
            public function fetchRows(string $url, int $chunkSize = 100): \Illuminate\Support\Collection
            {
                return collect([
                    [
                        'source_name' => '(Almh.) Hj. Munafi\'ah',
                        'normalized_name' => 'MUNAFI AH',
                        'gender_id' => 2,
                        'dob' => '1936-01-01',
                        'yob' => '1936',
                    ],
                    [
                        'source_name' => 'Yusrul Hana',
                        'normalized_name' => 'YUSRUL HANA',
                        'gender_id' => 1,
                        'dob' => '1981-09-20',
                        'yob' => '1981',
                    ],
                ]);
            }
        });

        $exitCode = $this->artisan('notion:sync-birth-dates', [
            'url' => self::NOTION_URL,
            '--apply' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertSame('1936-01-01', optional($mother->fresh()->dob)->format('Y-m-d'));
        $this->assertSame('1936', (string) $mother->fresh()->yob);
        $this->assertSame('1981-09-20', optional($son->fresh()->dob)->format('Y-m-d'));
        $this->assertSame('1981', (string) $son->fresh()->yob);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
