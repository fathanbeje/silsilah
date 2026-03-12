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
            ->select(['id', 'name', 'nickname', 'gender_id', 'dob', 'yob'])
            ->get();

        $byName = [];
        $byNameGender = [];

        foreach ($users as $user) {
            $aliases = collect([
                $this->notionBirthDateSource->normalizeComparableName($user->name),
                $this->notionBirthDateSource->normalizeComparableName($user->nickname),
            ])->filter()->unique()->values();

            foreach ($aliases as $alias) {
                $byName[$alias][$user->id] = $user;
                $byNameGender[$alias.'|'.$user->gender_id][$user->id] = $user;
            }
        }

        return [
            'by_name' => collect($byName)->map(fn ($items) => collect($items)->values()),
            'by_name_gender' => collect($byNameGender)->map(fn ($items) => collect($items)->values()),
        ];
    }

    private function matchUsers(array $row, array $indexes): Collection
    {
        $normalizedName = $row['normalized_name'] ?? null;
        if (! $normalizedName) {
            return collect();
        }

        $matches = collect();
        if (! empty($row['gender_id'])) {
            $matches = $indexes['by_name_gender']->get($normalizedName.'|'.$row['gender_id'], collect());
        }

        if ($matches->isEmpty()) {
            $matches = $indexes['by_name']->get($normalizedName, collect());
        }

        return $matches->unique('id')->values();
    }
}
