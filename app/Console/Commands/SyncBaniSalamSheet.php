<?php

namespace App\Console\Commands;

use App\Couple;
use App\Services\BaniSalamSheetSource;
use App\Services\ParentCoupleResolver;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Ramsey\Uuid\Uuid;

class SyncBaniSalamSheet extends Command
{
    protected $signature = 'sheet:sync-bani-salam
        {path : Path JSON hasil ekspor sheet Bani Salam}
        {--apply : Simpan perubahan ke database}
        {--create-missing : Buat user baru bila belum ada}
        {--dump-json= : Simpan hasil normalisasi ke file JSON}';

    protected $description = 'Sinkronkan sheet Bani Salam yang sudah diekspor ke JSON';

    public function __construct(
        private BaniSalamSheetSource $sheetSource,
        private ParentCoupleResolver $parentCoupleResolver
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $createMissing = (bool) $this->option('create-missing');
        $rows = $this->sheetSource->loadRows($this->argument('path'));

        if ($dumpPath = $this->option('dump-json')) {
            $this->dumpNormalizedJson($rows, $dumpPath);
            $this->info('JSON normalisasi disimpan: '.$dumpPath);
        }

        $indexes = $this->buildUserIndexes();
        $rowUserMap = [];

        $matched = 0;
        $created = 0;
        $updated = 0;
        $missing = 0;
        $ambiguous = 0;
        $relationSynced = 0;
        $relatedCreated = 0;

        foreach ($rows as $row) {
            $matches = $this->matchUsers(
                $row,
                $indexes,
                array_merge($row['parent_aliases'] ?? [], $row['spouse_aliases'] ?? [], $row['child_aliases'] ?? [])
            );

            if ($matches->isEmpty()) {
                if ($createMissing) {
                    $user = $this->createUserFromEntity($row, $apply);
                    if ($user) {
                        $created++;
                        $rowUserMap[$row['normalized_name']] = $user->id;
                        $this->addUserToIndexes($user, $indexes);
                        continue;
                    }
                }

                $missing++;
                $this->warn('User belum ditemukan: '.$row['source_name']);
                continue;
            }

            if ($matches->count() > 1) {
                $ambiguous++;
                $this->warn('User ambigu: '.$row['source_name'].' cocok '.$matches->count().' record');
                continue;
            }

            /** @var \App\User $user */
            $user = $matches->first();
            $rowUserMap[$row['normalized_name']] = $user->id;
            $matched++;

            if ($this->updateUserFromRow($user, $row, $apply)) {
                $updated++;
            }
        }

        if ($apply && ! empty($rowUserMap)) {
            ['synced' => $relationSynced, 'created' => $relatedCreated] = $this->syncRelations($rows, $indexes, $createMissing);
        }

        $this->newLine();
        $this->info('Ringkasan');
        $this->line('Baris valid: '.$rows->count());
        $this->line('Matched: '.$matched);
        $this->line('Updated: '.$updated);
        $this->line('Created: '.$created);
        $this->line('Relasi tersinkron: '.$relationSynced);
        $this->line('User terkait dibuat: '.$relatedCreated);
        $this->line('Missing: '.$missing);
        $this->line('Ambiguous: '.$ambiguous);
        $this->line('Mode: '.($apply ? 'apply' : 'dry-run'));

        return self::SUCCESS;
    }

    private function dumpNormalizedJson(Collection $rows, string $dumpPath): void
    {
        $directory = dirname($dumpPath);
        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($dumpPath, json_encode($rows->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function buildUserIndexes(): array
    {
        $users = User::query()
            ->with([
                'father:id,name,nickname,gender_id',
                'mother:id,name,nickname,gender_id',
                'couples:id,name,nickname,gender_id',
            ])
            ->select(['id', 'name', 'nickname', 'gender_id', 'yob', 'city', 'address', 'father_id', 'mother_id', 'parent_id'])
            ->get();

        $byName = [];
        $byNameGender = [];
        $aliasesByUser = [];
        $contextByUser = [];

        foreach ($users as $user) {
            $aliases = collect([
                $this->sheetSource->comparableNameVariants($user->name),
                $this->sheetSource->comparableNameVariants($user->nickname),
            ])->flatten()->filter()->unique()->values();

            $contextAliases = collect([
                $user->father?->name,
                $user->mother?->name,
            ])
                ->merge($user->couples->pluck('name'))
                ->flatMap(fn ($name) => $this->sheetSource->comparableNameVariants($name))
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

    private function matchUsers(array $entity, array $indexes, array $contextAliases = []): Collection
    {
        $nameAliases = collect($entity['name_aliases'] ?? [])
            ->filter()
            ->values();

        if ($nameAliases->isEmpty() && ! empty($entity['normalized_name'])) {
            $nameAliases = collect([$entity['normalized_name']]);
        }

        if ($nameAliases->isEmpty()) {
            return collect();
        }

        $matches = collect();
        foreach ($nameAliases as $alias) {
            if (! empty($entity['gender_id'])) {
                $matches = $matches->merge($indexes['by_name_gender']->get($alias.'|'.$entity['gender_id'], collect()));
            }

            $matches = $matches->merge($indexes['by_name']->get($alias, collect()));
        }

        $matches = $this->filterMatchesByContext($matches->unique('id')->values(), $contextAliases, $indexes);
        if ($matches->isNotEmpty()) {
            return $matches;
        }

        return $this->fuzzyMatchUsers($entity, $indexes, $contextAliases);
    }

    private function fuzzyMatchUsers(array $entity, array $indexes, array $contextAliases = []): Collection
    {
        $rowAliases = collect($entity['name_aliases'] ?? [])
            ->filter()
            ->values();

        if ($rowAliases->isEmpty()) {
            return collect();
        }

        $rowContext = collect($contextAliases)->filter()->unique()->values();

        $scored = $indexes['users']
            ->filter(function (User $user) use ($entity) {
                return empty($entity['gender_id']) || (int) $user->gender_id === (int) $entity['gender_id'];
            })
            ->map(function (User $user) use ($rowAliases, $rowContext, $indexes) {
                $userAliases = $indexes['aliases_by_user']->get($user->id, collect());
                $contextForUser = $indexes['context_by_user']->get($user->id, collect());

                $score = 0.0;
                foreach ($rowAliases as $rowAlias) {
                    foreach ($userAliases as $userAlias) {
                        similar_text($rowAlias, $userAlias, $percent);
                        $score = max($score, $percent);
                    }
                }

                return [
                    'user' => $user,
                    'score' => $score,
                    'context_overlap' => $rowContext->intersect($contextForUser)->count(),
                    'has_family_links' => ! empty($user->father_id)
                        || ! empty($user->mother_id)
                        || ! empty($user->parent_id)
                        || $user->couples->isNotEmpty(),
                ];
            })
            ->filter(function (array $candidate) use ($rowContext) {
                if ($rowContext->isNotEmpty() && $candidate['context_overlap'] === 0 && $candidate['has_family_links']) {
                    return false;
                }

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

    private function createUserFromEntity(array $entity, bool $apply): ?User
    {
        if (empty($entity['name']) || empty($entity['gender_id'])) {
            return null;
        }

        $user = new User();
        $user->id = Uuid::uuid4()->toString();
        $user->name = $entity['name'];
        $user->nickname = $entity['nickname'] ?? $entity['name'];
        $user->gender_id = (int) $entity['gender_id'];
        $user->yob = $entity['yob'] ?? null;
        $user->city = $entity['city'] ?? null;
        $user->address = $entity['address'] ?? null;
        $user->is_deceased = ! empty($entity['is_deceased']);

        $this->line('[create] '.$user->name.' | gender '.$user->gender_id.' | yob '.($user->yob ?: 'NULL'));

        if ($apply) {
            $user->save();
        }

        return $user;
    }

    private function updateUserFromRow(User $user, array $row, bool $apply): bool
    {
        $dirty = false;

        if (! $user->nickname && ! empty($row['nickname'])) {
            $user->nickname = $row['nickname'];
            $dirty = true;
        }

        if (! $user->yob && ! empty($row['yob'])) {
            $user->yob = $row['yob'];
            $dirty = true;
        }

        if (! $user->city && ! empty($row['city'])) {
            $user->city = $row['city'];
            $dirty = true;
        }

        if (! $user->address && ! empty($row['address'])) {
            $user->address = $row['address'];
            $dirty = true;
        }

        if (! $user->is_deceased && ! empty($row['is_deceased'])) {
            $user->is_deceased = true;
            $dirty = true;
        }

        if ($dirty) {
            $this->line('[update] '.$user->name);
            if ($apply) {
                $user->save();
            }
        }

        return $dirty;
    }

    private function addUserToIndexes(User $user, array &$indexes): void
    {
        $aliases = collect([
            $this->sheetSource->comparableNameVariants($user->name),
            $this->sheetSource->comparableNameVariants($user->nickname),
        ])->flatten()->filter()->unique()->values();

        $indexes['users']->push($user);
        $indexes['aliases_by_user']->put($user->id, $aliases);
        $indexes['context_by_user']->put($user->id, collect());

        foreach ($aliases as $alias) {
            $indexes['by_name']->put(
                $alias,
                $indexes['by_name']->get($alias, collect())->push($user)->unique('id')->values()
            );
            $indexes['by_name_gender']->put(
                $alias.'|'.$user->gender_id,
                $indexes['by_name_gender']->get($alias.'|'.$user->gender_id, collect())->push($user)->unique('id')->values()
            );
        }
    }

    private function syncRelations(Collection $rows, array &$indexes, bool $createMissing): array
    {
        $synced = 0;
        $created = 0;

        foreach ($rows as $row) {
            $matches = $this->matchUsers($row, $indexes, array_merge($row['parent_aliases'] ?? [], $row['spouse_aliases'] ?? []));
            if ($matches->count() !== 1) {
                continue;
            }

            /** @var \App\User $user */
            $user = User::query()->with(['couples', 'father', 'mother'])->find($matches->first()->id);
            if (! $user) {
                continue;
            }

            $before = $this->relationSignature($user);

            $fatherEntity = collect($row['parents'])->first(fn (array $parent) => ($parent['role'] ?? null) === 'father');
            $motherEntity = collect($row['parents'])->first(fn (array $parent) => ($parent['role'] ?? null) === 'mother');

            $father = $this->resolveEntityUser($fatherEntity, $indexes, $createMissing, 1, array_merge($row['name_aliases'], $row['spouse_aliases'] ?? []), $created);
            $mother = $this->resolveEntityUser($motherEntity, $indexes, $createMissing, 2, array_merge($row['name_aliases'], $row['spouse_aliases'] ?? []), $created);

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

            $resolvedCouples = collect();
            foreach ($row['spouses'] as $spouseEntity) {
                $hintGender = empty($user->gender_id) ? null : ((int) $user->gender_id === 1 ? 2 : 1);
                $spouse = $this->resolveEntityUser($spouseEntity, $indexes, $createMissing, $hintGender, array_merge($row['name_aliases'], $row['child_aliases'] ?? []), $created);
                if (! $spouse || $spouse->id === $user->id || (int) $spouse->gender_id === (int) $user->gender_id) {
                    continue;
                }

                $couple = Couple::firstOrCreate(
                    [
                        'husband_id' => (int) $user->gender_id === 1 ? $user->id : $spouse->id,
                        'wife_id' => (int) $user->gender_id === 2 ? $user->id : $spouse->id,
                    ],
                    [
                        'id' => Uuid::uuid4()->toString(),
                        'manager_id' => $user->manager_id ?: $spouse->manager_id,
                        'spouse_order' => $spouseEntity['order'] ?? null,
                    ]
                );

                if (empty($couple->spouse_order) && ! empty($spouseEntity['order'])) {
                    $couple->spouse_order = $spouseEntity['order'];
                    $couple->save();
                }

                $resolvedCouples->push($couple);
            }

            if ($resolvedCouples->count() === 1) {
                /** @var \App\Couple $couple */
                $couple = $resolvedCouples->first();
                foreach ($row['children'] as $childEntity) {
                    $child = $this->resolveEntityUser($childEntity, $indexes, $createMissing, $childEntity['gender_id'] ?? null, array_merge($row['name_aliases'], $row['spouse_aliases'] ?? []), $created);
                    if (! $child || ! $this->canAssignChildToCouple($child, $couple)) {
                        continue;
                    }

                    $child->father_id = $couple->husband_id;
                    $child->mother_id = $couple->wife_id;
                    $child->parent_id = $couple->id;
                    if (! $child->is_deceased && ! empty($childEntity['is_deceased'])) {
                        $child->is_deceased = true;
                    }
                    $child->save();
                    $this->parentCoupleResolver->syncUser($child);
                }
            }

            $after = $this->relationSignature($user->fresh(['couples', 'father', 'mother']));
            if ($before !== $after) {
                $synced++;
            }
        }

        return compact('synced', 'created');
    }

    private function resolveEntityUser(?array $entity, array &$indexes, bool $createMissing, ?int $genderHint = null, array $contextAliases = [], ?int &$createdCounter = null): ?User
    {
        if (! $entity || empty($entity['name'])) {
            return null;
        }

        if ($genderHint && empty($entity['gender_id'])) {
            $entity['gender_id'] = $genderHint;
        }

        $exactMatches = User::query()
            ->with([
                'father:id,name,nickname,gender_id',
                'mother:id,name,nickname,gender_id',
                'couples:id,name,nickname,gender_id',
            ])
            ->when(! empty($entity['gender_id']), function ($query) use ($entity) {
                $query->where('gender_id', $entity['gender_id']);
            })
            ->where('name', $entity['name'])
            ->get();

        $exactMatches = $this->filterMatchesByContext($exactMatches, $contextAliases, $indexes);

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        $matches = $this->matchUsers($entity, $indexes, $contextAliases);
        if ($matches->count() === 1) {
            $user = $matches->first();
            if (! $user->is_deceased && ! empty($entity['is_deceased'])) {
                $user->is_deceased = true;
                $user->save();
            }

            return $user;
        }

        if (! $createMissing || empty($entity['gender_id'])) {
            return null;
        }

        $created = $this->createUserFromEntity([
            'name' => $entity['name'],
            'nickname' => $entity['name'],
            'gender_id' => $entity['gender_id'],
            'is_deceased' => $entity['is_deceased'] ?? false,
        ], true);

        if (! $created) {
            return null;
        }

        $this->addUserToIndexes($created, $indexes);
        if (! is_null($createdCounter)) {
            $createdCounter++;
        }

        return $created;
    }

    private function relationSignature(User $user): string
    {
        return implode('|', [
            $user->father_id ?: 'null',
            $user->mother_id ?: 'null',
            $user->parent_id ?: 'null',
            $user->couples->pluck('pivot.id')->filter()->sort()->implode(','),
        ]);
    }

    private function canAssignChildToCouple(User $child, Couple $couple): bool
    {
        if ($child->parent_id && $child->parent_id !== $couple->id) {
            return false;
        }

        if ($child->father_id && $child->father_id !== $couple->husband_id) {
            return false;
        }

        if ($child->mother_id && $child->mother_id !== $couple->wife_id) {
            return false;
        }

        return true;
    }

    private function filterMatchesByContext(Collection $matches, array $contextAliases, array $indexes): Collection
    {
        $matches = $matches->unique('id')->values();
        $rowContext = collect($contextAliases)->filter()->unique()->values();

        if ($matches->isEmpty() || $rowContext->isEmpty()) {
            return $matches;
        }

        $contextMatched = $matches->filter(function (User $user) use ($rowContext, $indexes) {
            $contextForUser = $this->contextAliasesForUser($user, $indexes);

            return $rowContext->intersect($contextForUser)->isNotEmpty();
        })->values();

        if ($contextMatched->isNotEmpty()) {
            return $contextMatched;
        }

        return $matches->filter(function (User $user) {
            return empty($user->father_id)
                && empty($user->mother_id)
                && empty($user->parent_id)
                && $user->couples->isEmpty();
        })->values();
    }

    private function contextAliasesForUser(User $user, array $indexes): Collection
    {
        $indexed = $indexes['context_by_user']->get($user->id, collect());
        $live = collect([
            $user->relationLoaded('father') ? $user->father?->name : null,
            $user->relationLoaded('mother') ? $user->mother?->name : null,
        ])->merge(
            $user->relationLoaded('couples')
                ? $user->couples->pluck('name')
                : collect()
        )->flatMap(fn ($name) => $this->sheetSource->comparableNameVariants($name))
            ->filter()
            ->unique()
            ->values();

        return $indexed->merge($live)->filter()->unique()->values();
    }
}
