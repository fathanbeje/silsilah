<?php

namespace App\Services;

use App\Support\FamilyViewBuilder;
use App\User;
use Carbon\CarbonImmutable;
use Hussainweb\DateConverter\Algorithm\GregorianAlgorithm;
use Hussainweb\DateConverter\Algorithm\Hijri\HijriFatimidAstronomical;
use Hussainweb\DateConverter\Value\GregorianDate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class DeathIndexBuilder
{
    private const MAX_DEPTH = 20;

    private HijriFatimidAstronomical $hijriAlgorithm;

    private GregorianAlgorithm $gregorianAlgorithm;

    public function __construct(
        private FamilyScopeResolver $familyScopeResolver,
        private FamilyViewBuilder $familyViewBuilder
    ) {
        $this->hijriAlgorithm = new HijriFatimidAstronomical();
        $this->gregorianAlgorithm = new GregorianAlgorithm();
    }

    public function build(string $tab = 'all'): array
    {
        $coreUser = $this->familyScopeResolver->coreUser();
        if (! $coreUser) {
            return $this->emptyPayload($tab);
        }

        $coreUser = $this->familyViewBuilder->loadTreeRelations($coreUser, self::MAX_DEPTH);
        $treeData = $this->familyViewBuilder->buildTreeData($coreUser, self::MAX_DEPTH);
        $this->hydrateAuxiliaryRelations($treeData['node']);

        $today = CarbonImmutable::today(config('app.timezone'));
        $currentHijri = $this->toHijri($today);
        $allRows = $this->flattenRows($treeData['node'], null)
            ->filter(fn (array $row) => ! empty($row['is_deceased']))
            ->values();

        $allRows = $this->sortAllRows($allRows);
        $haulRows = $this->sortHaulRows(
            $allRows->filter(function (array $row) use ($currentHijri) {
                return $row['is_haul_available'] && $row['hijri_month'] === $currentHijri->getMonth();
            })->values()
        );

        return [
            'coreUser' => $coreUser,
            'activeTab' => in_array($tab, ['all', 'haul-bulan-ini'], true) ? $tab : 'all',
            'allRows' => $allRows,
            'allGroups' => $allRows->groupBy('generation_depth')->map(function (Collection $rows, int $depth) {
                return [
                    'label' => $depth.'. '.$this->generationLabel($depth),
                    'count' => $rows->count(),
                    'rows' => $rows->values(),
                ];
            }),
            'haulRows' => $haulRows,
            'currentHijriMonthBadge' => $currentHijri->getFormatter()->format('M').' '.$currentHijri->getYear().' H',
        ];
    }

    private function hydrateAuxiliaryRelations(array $node): void
    {
        $users = new EloquentCollection($this->collectNodeUsers($node)->unique('id')->values()->all());
        if ($users->isEmpty()) {
            return;
        }

        $users->loadMissing([
            'metadata',
            'father',
            'mother',
            'parent.husband',
            'parent.wife',
        ]);
    }

    private function collectNodeUsers(array $node): Collection
    {
        $users = collect([$node['user']])->merge(collect($node['spouse_labels'] ?? []));

        foreach ($node['children'] ?? [] as $childNode) {
            $users = $users->merge($this->collectNodeUsers($childNode));
        }

        return $users->filter();
    }

    private function flattenRows(array $node, ?User $branchRootUser): Collection
    {
        $rows = collect();
        $generationDepth = max(0, ((int) ($node['node_depth'] ?? 1)) - 1);
        $user = $node['user'];

        $currentBranchRoot = $branchRootUser;
        if ($generationDepth === 1) {
            $currentBranchRoot = $user;
        }

        if ($generationDepth >= 1 && $user->isDeceased()) {
            $rows->push($this->makeRow($user, $generationDepth, 'Kandung', $currentBranchRoot));
        }

        if ($generationDepth >= 1) {
            foreach (collect($node['spouse_labels'] ?? []) as $spouse) {
                if (! $spouse || ! $spouse->isDeceased()) {
                    continue;
                }

                $rows->push($this->makeRow($spouse, $generationDepth, 'Menantu', $currentBranchRoot));
            }
        }

        foreach ($node['children'] ?? [] as $childNode) {
            $rows = $rows->merge($this->flattenRows($childNode, $currentBranchRoot));
        }

        return $rows;
    }

    private function makeRow(User $user, int $generationDepth, string $relationshipType, ?User $branchRootUser): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $todayHijri = $this->toHijri($today);
        $deathDate = $user->dod ? CarbonImmutable::instance($user->dod)->startOfDay() : null;
        $hijriDate = $deathDate ? $this->toHijri($deathDate) : null;
        $currentHaulGregorian = $hijriDate ? $this->haulGregorianForYear($hijriDate, $todayHijri->getYear()) : null;
        $nextHaulGregorian = $hijriDate ? $this->nextHaulGregorian($hijriDate, $today, $todayHijri, $currentHaulGregorian) : null;
        $countdownDays = $this->countdownDays($today, $todayHijri, $hijriDate, $currentHaulGregorian, $nextHaulGregorian);

        return [
            'id' => $user->id,
            'name' => $user->display_name,
            'gender_code' => $user->gender,
            'parent_label' => $this->parentLabel($user),
            'generation_depth' => $generationDepth,
            'generation_label' => $this->generationLabel($generationDepth),
            'generation_group_label' => $generationDepth.'. '.$this->generationLabel($generationDepth),
            'nasab_label' => $branchRootUser?->display_name ?: 'Cabang tidak diketahui',
            'relationship_type' => $relationshipType,
            'death_date_label' => $deathDate ? $deathDate->format('Y-m-d') : ($user->yod ?: 'Tidak tersedia'),
            'hijri_year' => $hijriDate?->getYear(),
            'hijri_month' => $hijriDate?->getMonth(),
            'hijri_month_name' => $hijriDate ? $hijriDate->getFormatter()->format('M') : null,
            'hijri_day' => $hijriDate?->getMonthDay(),
            'hijri_haul_label' => $hijriDate
                ? $hijriDate->getMonthDay().' '.$hijriDate->getFormatter()->format('M').' '.$hijriDate->getYear().' H'
                : 'Tidak tersedia',
            'next_haul_gregorian' => $nextHaulGregorian?->format('Y-m-d'),
            'haul_countdown_days' => $countdownDays,
            'haul_countdown_label' => $this->countdownLabel($countdownDays),
            'is_haul_available' => ! is_null($hijriDate),
            'cemetery_location_label' => $this->cemeteryLocationLabel($user),
            'is_deceased' => $user->isDeceased(),
        ];
    }

    private function sortAllRows(Collection $rows): Collection
    {
        return $rows->sort(function (array $left, array $right) {
            $leftHubOrder = $left['relationship_type'] === 'Kandung' ? 0 : 1;
            $rightHubOrder = $right['relationship_type'] === 'Kandung' ? 0 : 1;

            return [$left['generation_depth'], $leftHubOrder, $left['death_date_label'], $left['name']]
                <=> [$right['generation_depth'], $rightHubOrder, $right['death_date_label'], $right['name']];
        })->values();
    }

    private function sortHaulRows(Collection $rows): Collection
    {
        return $rows->sort(function (array $left, array $right) {
            return [$left['hijri_day'], $left['haul_countdown_days'], $left['generation_depth'], $left['name']]
                <=> [$right['hijri_day'], $right['haul_countdown_days'], $right['generation_depth'], $right['name']];
        })->values();
    }

    private function generationLabel(int $depth): string
    {
        return [
            1 => 'Anak',
            2 => 'Cucu',
            3 => 'Buyut',
            4 => 'Canggah',
            5 => 'Wareng',
            6 => 'Udheg-udheg',
        ][$depth] ?? 'Generasi '.$depth;
    }

    private function parentLabel(User $user): string
    {
        $labels = collect([
            optional($user->father)->display_name,
            optional($user->mother)->display_name,
        ])->filter()->unique()->values();

        return $labels->isNotEmpty() ? $labels->implode(' / ') : 'Tidak tersedia';
    }

    private function cemeteryLocationLabel(User $user): string
    {
        return $user->getMetadata('cemetery_location_name')
            ?: $user->getMetadata('cemetery_location_address')
            ?: 'Tidak tersedia';
    }

    private function countdownLabel(?int $days): string
    {
        if (is_null($days)) {
            return 'Tidak tersedia';
        }

        if ($days === 0) {
            return 'Hari ini';
        }

        if ($days < 0) {
            return abs($days).' hari yang lalu';
        }

        return $days.' hari lagi';
    }

    private function countdownDays(
        CarbonImmutable $today,
        $todayHijri,
        $hijriDate,
        ?CarbonImmutable $currentHaulGregorian,
        ?CarbonImmutable $nextHaulGregorian
    ): ?int {
        if (! $hijriDate) {
            return null;
        }

        if (
            $currentHaulGregorian
            && $hijriDate->getMonth() === $todayHijri->getMonth()
            && $currentHaulGregorian->lt($today)
        ) {
            return -1 * $currentHaulGregorian->diffInDays($today);
        }

        return $nextHaulGregorian ? $today->diffInDays($nextHaulGregorian, false) : null;
    }

    private function toHijri(CarbonImmutable $gregorianDate)
    {
        $gregorian = new GregorianDate(
            (int) $gregorianDate->format('j'),
            (int) $gregorianDate->format('n'),
            (int) $gregorianDate->format('Y')
        );

        return $this->hijriAlgorithm->fromJulianDay(
            $this->gregorianAlgorithm->toJulianDay($gregorian)
        );
    }

    private function nextHaulGregorian($hijriDate, CarbonImmutable $today, $todayHijri, ?CarbonImmutable $currentHaulGregorian = null): CarbonImmutable
    {
        $candidateYear = $todayHijri->getYear();

        $candidateGregorian = $currentHaulGregorian ?: $this->haulGregorianForYear($hijriDate, $candidateYear);

        if ($candidateGregorian->lt($today)) {
            $candidateGregorian = $this->haulGregorianForYear($hijriDate, $candidateYear + 1);
        }

        return $candidateGregorian;
    }

    private function haulGregorianForYear($hijriDate, int $hijriYear): CarbonImmutable
    {
        $candidateHijri = $this->hijriAlgorithm->constructDateValue(
            $hijriDate->getMonthDay(),
            $hijriDate->getMonth(),
            $hijriYear
        );

        return $this->toGregorian($candidateHijri);
    }

    private function toGregorian($hijriDate): CarbonImmutable
    {
        $gregorian = $this->gregorianAlgorithm->fromJulianDay(
            $this->hijriAlgorithm->toJulianDay($hijriDate)
        );

        return CarbonImmutable::create(
            $gregorian->getYear(),
            $gregorian->getMonth(),
            $gregorian->getMonthDay(),
            0,
            0,
            0,
            config('app.timezone')
        );
    }

    private function emptyPayload(string $tab): array
    {
        return [
            'coreUser' => null,
            'activeTab' => in_array($tab, ['all', 'haul-bulan-ini'], true) ? $tab : 'all',
            'allRows' => collect(),
            'allGroups' => collect(),
            'haulRows' => collect(),
            'currentHijriMonthBadge' => null,
        ];
    }
}
