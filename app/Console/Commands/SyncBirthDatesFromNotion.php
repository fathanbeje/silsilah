<?php

namespace App\Console\Commands;

use App\Services\NotionPublicBirthDateSource;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncBirthDatesFromNotion extends Command
{
    protected $signature = 'notion:sync-birth-dates
        {url : URL database Notion publik}
        {--apply : Simpan perubahan ke database}
        {--chunk=100 : Jumlah block Notion per request}
        {--fill-empty-only : Hanya isi user yang tanggal/tahun lahirnya masih kosong}';

    protected $description = 'Sinkronkan tanggal lahir dari database Notion publik';

    public function __construct(private NotionPublicBirthDateSource $notionBirthDateSource)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $fillEmptyOnly = (bool) $this->option('fill-empty-only');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $rows = $this->notionBirthDateSource->fetchRows($this->argument('url'), $chunkSize);
        $userIndexes = $this->buildUserIndexes();

        $updated = 0;
        $unchanged = 0;
        $missing = 0;
        $ambiguous = 0;
        $skippedExisting = 0;

        foreach ($rows as $row) {
            $matches = $this->matchUsers($row, $userIndexes);

            if ($matches->isEmpty()) {
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

            if ($fillEmptyOnly && ($user->dob || $user->yob)) {
                $skippedExisting++;
                continue;
            }

            if ($user->dob === $row['dob'] && (string) $user->yob === (string) $row['yob']) {
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
                $user->save();
            }

            $updated++;
        }

        $this->newLine();
        $this->info('Ringkasan');
        $this->line('Baris Notion valid: '.$rows->count());
        $this->line('Updated: '.$updated);
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
}
