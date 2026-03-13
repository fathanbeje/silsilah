<?php

namespace App\Console\Commands;

use App\Couple;
use App\Services\ParentCoupleResolver;
use App\Services\NotionPublicBirthDateSource;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class SyncBirthDatesFromNotion extends Command
{
    protected $signature = 'notion:sync-birth-dates
        {url : URL database Notion publik}
        {--apply : Simpan perubahan ke database}
        {--chunk=100 : Jumlah block Notion per request}
        {--fill-empty-only : Hanya isi user yang tanggal/tahun lahirnya masih kosong}
        {--create-missing : Buat user baru untuk baris Notion yang belum ada di database}';

    protected $description = 'Sinkronkan tanggal lahir dari database Notion publik';

    public function __construct(
        private NotionPublicBirthDateSource $notionBirthDateSource,
        private ParentCoupleResolver $parentCoupleResolver
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $fillEmptyOnly = (bool) $this->option('fill-empty-only');
        $createMissing = (bool) $this->option('create-missing');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $rows = $this->notionBirthDateSource->fetchRows($this->argument('url'), $chunkSize);
        $userIndexes = $this->buildUserIndexes();
        $rowUserMap = [];

        $updated = 0;
        $unchanged = 0;
        $missing = 0;
        $ambiguous = 0;
        $skippedExisting = 0;
        $created = 0;
        $relationSynced = 0;

        foreach ($rows as $row) {
            $rowKey = $this->resolveRowKey($row);
            $matches = $this->matchUsers($row, $userIndexes);

            if ($matches->isEmpty()) {
                if ($createMissing) {
                    $user = $this->createUserFromRow($row, $apply);
                    if ($user) {
                        $rowUserMap[$rowKey] = $user->id;
                        $this->addUserToIndexes($user, $userIndexes);
                        $created++;
                        continue;
                    }
                }

                $missing++;
                $this->warn('User tidak ditemukan: '.$row['source_name']);
                continue;
            }

            if ($matches->count() > 1) {
                $ambiguous++;
                $this->warn('User ambigu: '.$row['source_name'].' cocok '.$matches->count().' record');
                continue;
            }

            /** @var \App\User $user */
            $user = $matches->first();
            $rowUserMap[$rowKey] = $user->id;

            if ($fillEmptyOnly && ($user->dob || $user->yob)) {
                if ($apply && ! empty($row['is_deceased']) && ! $user->is_deceased) {
                    $user->is_deceased = true;
                    $user->save();
                }
                $skippedExisting++;
                continue;
            }

            $shouldMarkDeceased = ! empty($row['is_deceased']) && ! $user->is_deceased;

            if ($user->dob === $row['dob'] && (string) $user->yob === (string) $row['yob'] && ! $shouldMarkDeceased) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '%s | dob %s -> %s | yob %s -> %s',
                $user->name,
                $user->dob ?: 'NULL',
                $row['dob'] ?: 'NULL',
                $user->yob ?: 'NULL',
                $row['yob'] ?: 'NULL'
            ));

            if ($apply) {
                $user->dob = $row['dob'];
                $user->yob = $row['yob'];
                if (! empty($row['is_deceased'])) {
                    $user->is_deceased = true;
                }
                $user->save();
            }

            $updated++;
        }

        if ($apply && ! empty($rowUserMap)) {
            $relationSynced = $this->syncRowRelations($rows, $rowUserMap);
        }

        $this->newLine();
        $this->info('Ringkasan');
        $this->line('Baris Notion valid: '.$rows->count());
        $this->line('Updated: '.$updated);
        $this->line('Created: '.$created);
        $this->line('Relasi tersinkron: '.$relationSynced);
        $this->line('Unchanged: '.$unchanged);
        $this->line('Skipped existing: '.$skippedExisting);
        $this->line('User tidak ditemukan: '.$missing);
        $this->line('User ambigu: '.$ambiguous);
        $this->line('Mode: '.($apply ? 'apply' : 'dry-run'));

        return self::SUCCESS;
    }

    private function buildUserIndexes(): array
    {
        $users = User::query()
            ->with([
                'father:id,name,nickname,gender_id',
                'mother:id,name,nickname,gender_id',
                'couples:id,name,nickname,gender_id',
            ])
            ->select(['id', 'name', 'nickname', 'gender_id', 'dob', 'yob', 'father_id', 'mother_id'])
            ->get();

        $byName = [];
        $byNameGender = [];
        $aliasesByUser = [];
        $contextByUser = [];

        foreach ($users as $user) {
            $aliases = collect([
                $this->notionBirthDateSource->comparableNameVariants($user->name),
                $this->notionBirthDateSource->comparableNameVariants($user->nickname),
            ])->flatten()->filter()->unique()->values();

            $contextAliases = collect([
                $user->father?->name,
                $user->mother?->name,
            ])
                ->merge($user->couples->pluck('name'))
                ->flatMap(fn ($name) => $this->notionBirthDateSource->comparableNameVariants($name))
                ->filter()
                ->unique()
                ->values();

            $aliasesByUser[$user->id] = $aliases;
            $contextByUser[$user->id] = $contextAliases;

            foreach ($aliases as $alias) {
                $byName[$alias][$user->id] = $user;
                $byNameGender[$alias.'|'.$user->gender_id][$user->id] = $user;
            }
        }

        return [
            'users' => $users,
            'by_name' => collect($byName)->map(fn ($items) => collect($items)->values()),
            'by_name_gender' => collect($byNameGender)->map(fn ($items) => collect($items)->values()),
            'aliases_by_user' => collect($aliasesByUser),
            'context_by_user' => collect($contextByUser),
        ];
    }

    private function matchUsers(array $row, array $indexes): Collection
    {
        $rowAliases = collect($row['name_aliases'] ?? [])
            ->filter()
            ->values();

        if ($rowAliases->isEmpty() && ! empty($row['normalized_name'])) {
            $rowAliases = collect([$row['normalized_name']]);
        }

        if ($rowAliases->isEmpty()) {
            return collect();
        }

        $matches = collect();
        foreach ($rowAliases as $alias) {
            if (! empty($row['gender_id'])) {
                $matches = $matches->merge($indexes['by_name_gender']->get($alias.'|'.$row['gender_id'], collect()));
            }

            $matches = $matches->merge($indexes['by_name']->get($alias, collect()));
        }

        $matches = $matches->unique('id')->values();

        if ($matches->isNotEmpty()) {
            return $matches;
        }

        return $this->fuzzyMatchUsers($row, $indexes);
    }

    private function fuzzyMatchUsers(array $row, array $indexes): Collection
    {
        $rowAliases = collect($row['name_aliases'] ?? [])
            ->filter()
            ->values();

        if ($rowAliases->isEmpty()) {
            return collect();
        }

        $rowContext = collect(array_merge(
            $row['parent_aliases'] ?? [],
            $row['spouse_aliases'] ?? []
        ))->filter()->unique()->values();

        $scored = $indexes['users']
            ->filter(function (User $user) use ($row) {
                return empty($row['gender_id']) || (int) $user->gender_id === (int) $row['gender_id'];
            })
            ->map(function (User $user) use ($rowAliases, $rowContext, $indexes) {
                $userAliases = $indexes['aliases_by_user']->get($user->id, collect());
                $contextAliases = $indexes['context_by_user']->get($user->id, collect());

                $score = 0.0;
                foreach ($rowAliases as $rowAlias) {
                    foreach ($userAliases as $userAlias) {
                        similar_text($rowAlias, $userAlias, $percent);
                        $score = max($score, $percent);
                    }
                }

                $contextOverlap = $rowContext->intersect($contextAliases)->count();

                return [
                    'user' => $user,
                    'score' => $score,
                    'context_overlap' => $contextOverlap,
                ];
            })
            ->filter(function (array $candidate) {
                if ($candidate['context_overlap'] > 0) {
                    return $candidate['score'] >= 70;
                }

                return $candidate['score'] >= 92;
            })
            ->sort(function (array $a, array $b) {
                return [$b['context_overlap'], $b['score']] <=> [$a['context_overlap'], $a['score']];
            })
            ->values();

        if ($scored->isEmpty()) {
            return collect();
        }

        $top = $scored->first();
        $second = $scored->get(1);

        $isClearlyBest = ! $second
            || $top['context_overlap'] > $second['context_overlap']
            || $top['score'] >= ($second['score'] + 5);

        if (! $isClearlyBest) {
            return collect();
        }

        return collect([$top['user']]);
    }

    private function createUserFromRow(array $row, bool $apply): ?User
    {
        $name = $this->sanitizeImportedName($row['source_name'] ?? null);
        if (! $name || empty($row['gender_id'])) {
            return null;
        }

        $user = new User();
        $user->id = Uuid::uuid4()->toString();
        $user->name = $name;
        $user->nickname = $name;
        $user->gender_id = (int) $row['gender_id'];
        $user->dob = $row['dob'] ?? null;
        $user->yob = $row['yob'] ?? null;
        $user->is_deceased = ! empty($row['is_deceased']);

        $this->line(sprintf(
            '[create] %s | dob %s | yob %s',
            $user->name,
            $user->dob ?: 'NULL',
            $user->yob ?: 'NULL'
        ));

        if ($apply) {
            $user->save();
        }

        return $user;
    }

    private function addUserToIndexes(User $user, array &$indexes): void
    {
        $aliases = collect([
            $this->notionBirthDateSource->comparableNameVariants($user->name),
            $this->notionBirthDateSource->comparableNameVariants($user->nickname),
        ])->flatten()->filter()->unique()->values();

        $indexes['users']->push($user);
        $indexes['aliases_by_user']->put($user->id, $aliases);
        $indexes['context_by_user']->put($user->id, collect());

        foreach ($aliases as $alias) {
            $byName = $indexes['by_name']->get($alias, collect())->push($user)->unique('id')->values();
            $indexes['by_name']->put($alias, $byName);

            $byNameGender = $indexes['by_name_gender']->get($alias.'|'.$user->gender_id, collect())->push($user)->unique('id')->values();
            $indexes['by_name_gender']->put($alias.'|'.$user->gender_id, $byNameGender);
        }
    }

    private function sanitizeImportedName(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/^\((?:Alm|Almh)\.?\)\s*/iu', '', $value);
        $value = preg_replace('/^(?:Alm|Almh)\.?\s+/iu', '', $value);
        $value = preg_replace('/^(?:(?:K\s*\.?\s*)?H(?:\s*\.?\s*)?(?:J(?:\s*\.?\s*)?)?\s+)+/iu', '', $value);
        $value = str_replace(['’', '‘', '`', '´'], "'", $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return User::normalizeUppercase($value);
    }

    private function syncRowRelations(Collection $rows, array $rowUserMap): int
    {
        $synced = 0;

        foreach ($rows as $row) {
            $userId = $rowUserMap[$this->resolveRowKey($row)] ?? null;
            if (! $userId) {
                continue;
            }

            /** @var User|null $user */
            $user = User::query()->with(['couples'])->find($userId);
            if (! $user) {
                continue;
            }

            $before = implode('|', [
                $user->father_id ?: 'null',
                $user->mother_id ?: 'null',
                $user->parent_id ?: 'null',
                $user->couples->pluck('id')->sort()->implode(','),
            ]);

            $relatedParents = collect($row['parent_block_ids'] ?? [])
                ->map(fn (string $blockId) => $rowUserMap[$blockId] ?? null)
                ->filter()
                ->map(fn (string $id) => User::find($id))
                ->filter();

            $father = $relatedParents->first(fn (User $candidate) => (int) $candidate->gender_id === 1);
            $mother = $relatedParents->first(fn (User $candidate) => (int) $candidate->gender_id === 2);

            if ($father && ! $user->father_id) {
                $user->father_id = $father->id;
            }

            if ($mother && ! $user->mother_id) {
                $user->mother_id = $mother->id;
            }

            if ($user->isDirty(['father_id', 'mother_id'])) {
                $user->save();
            }

            $this->parentCoupleResolver->syncUser($user);

            foreach (collect($row['spouse_block_ids'] ?? [])->map(fn (string $blockId) => $rowUserMap[$blockId] ?? null)->filter()->unique() as $spouseId) {
                /** @var User|null $spouse */
                $spouse = User::find($spouseId);
                if (! $spouse || $spouse->id === $user->id || (int) $spouse->gender_id === (int) $user->gender_id) {
                    continue;
                }

                [$husbandId, $wifeId] = (int) $user->gender_id === 1
                    ? [$user->id, $spouse->id]
                    : [$spouse->id, $user->id];

                Couple::firstOrCreate(
                    ['husband_id' => $husbandId, 'wife_id' => $wifeId],
                    ['id' => Uuid::uuid4()->toString(), 'manager_id' => $user->manager_id ?: $spouse->manager_id]
                );
            }

            $fresh = User::query()->with(['couples'])->find($user->id);
            $after = implode('|', [
                $fresh->father_id ?: 'null',
                $fresh->mother_id ?: 'null',
                $fresh->parent_id ?: 'null',
                $fresh->couples->pluck('id')->sort()->implode(','),
            ]);

            if ($before !== $after) {
                $synced++;
            }
        }

        return $synced;
    }

    private function resolveRowKey(array $row): string
    {
        return (string) ($row['block_id'] ?? $row['normalized_name'] ?? $row['source_name'] ?? Uuid::uuid4()->toString());
    }
}
