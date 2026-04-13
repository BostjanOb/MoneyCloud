<?php

namespace App\Services;

use App\Models\Bonus;
use App\Models\MonthlyPortfolioSnapshot;
use App\Models\PaycheckYear;
use App\Models\Person;
use Illuminate\Support\Collection;

class PaycheckGrowthStatisticsService
{
    /**
     * @return array{
     *     filters: array<int, array{label: string, value: string}>,
     *     selectedPerson: string,
     *     includeBonusesDefault: bool,
     *     rows: array<int, array{
     *         year: int,
     *         is_partial: bool,
     *         recorded_through_month: int|null,
     *         net: string,
     *         gross: string,
     *         bonuses_gross: string,
     *         bonuses_net: string,
     *         gross_with_bonuses: string,
     *         net_with_bonuses: string,
     *         cumulative_net: array<int, string>,
     *         cumulative_gross: array<int, string>,
     *         cumulative_bonuses_gross: array<int, string>,
     *         cumulative_bonuses_net: array<int, string>
     *     }>,
     *     chartSeries: array<int, array{key: string, label: string, color: string, values: array<int, float>}>,
     *     summary: array{
     *         latest_year: int|null,
     *         previous_year: int|null,
     *         net_change_amount: string|null,
     *         net_change_percentage: string|null,
     *         gross_change_amount: string|null,
     *         gross_change_percentage: string|null
     *     }
     * }
     */
    public function pageData(): array
    {
        $activePeople = Person::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        $selectedPerson = $this->resolveSelectedPerson($activePeople, (string) request()->query('person', 'all'));
        $paycheckYears = $this->paycheckYears($activePeople, $selectedPerson);
        $expectedPeopleCount = $selectedPerson === 'all' ? $activePeople->count() : 1;
        $rows = $this->buildRows($paycheckYears, $expectedPeopleCount);

        return [
            'filters' => $this->filters($activePeople),
            'selectedPerson' => $selectedPerson,
            'includeBonusesDefault' => false,
            'rows' => $rows,
            'chartSeries' => $this->buildChartSeries($rows),
            'summary' => $this->buildSummary($rows),
        ];
    }

    /**
     * @param  Collection<int, Person>  $activePeople
     * @return Collection<int, PaycheckYear>
     */
    private function paycheckYears(Collection $activePeople, string $selectedPerson): Collection
    {
        $query = PaycheckYear::query()
            ->with([
                'person:id,slug,name,is_active,sort_order',
                'paychecks:id,paycheck_year_id,month,net,gross',
                'bonuses:id,paycheck_year_id,amount,taxable,paid_tax',
            ]);

        if ($selectedPerson === 'all') {
            $query->whereIn('person_id', $activePeople->pluck('id'));
        } else {
            $query->whereHas(
                'person',
                fn ($personQuery) => $personQuery->where('slug', $selectedPerson)->where('is_active', true),
            );
        }

        return $query
            ->orderBy('year')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Person>  $activePeople
     * @return array<int, array{label: string, value: string}>
     */
    private function filters(Collection $activePeople): array
    {
        return [
            [
                'label' => 'Vsi skupaj',
                'value' => 'all',
            ],
            ...$activePeople
                ->map(fn (Person $person): array => [
                    'label' => $person->name,
                    'value' => $person->slug,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Person>  $activePeople
     */
    private function resolveSelectedPerson(Collection $activePeople, string $selectedPerson): string
    {
        if ($selectedPerson === 'all') {
            return 'all';
        }

        return $activePeople->contains(fn (Person $person): bool => $person->slug === $selectedPerson)
            ? $selectedPerson
            : 'all';
    }

    /**
     * @param  Collection<int, PaycheckYear>  $paycheckYears
     * @return array<int, array{
     *     year: int,
     *     is_partial: bool,
     *     recorded_through_month: int|null,
     *     net: string,
     *     gross: string,
     *     bonuses_gross: string,
     *     bonuses_net: string,
     *     gross_with_bonuses: string,
     *     net_with_bonuses: string,
     *     cumulative_net: array<int, string>,
     *     cumulative_gross: array<int, string>,
     *     cumulative_bonuses_gross: array<int, string>,
     *     cumulative_bonuses_net: array<int, string>
     * }>
     */
    private function buildRows(Collection $paycheckYears, int $expectedPeopleCount): array
    {
        if ($paycheckYears->isEmpty()) {
            return [];
        }

        $firstYear = (int) $paycheckYears->min('year');
        $rowMap = [];
        $currentYear = now()->year;
        $currentYearPeople = [];

        foreach (range($firstYear, $currentYear) as $year) {
            $rowMap[$year] = [
                'year' => $year,
                'is_partial' => false,
                'recorded_through_month' => null,
                'net' => '0.00',
                'gross' => '0.00',
                'bonuses_gross' => '0.00',
                'bonuses_net' => '0.00',
                'gross_with_bonuses' => '0.00',
                'net_with_bonuses' => '0.00',
                'cumulative_net' => $this->emptyMonthlyAmounts(),
                'cumulative_gross' => $this->emptyMonthlyAmounts(),
                'cumulative_bonuses_gross' => $this->emptyMonthlyAmounts(),
                'cumulative_bonuses_net' => $this->emptyMonthlyAmounts(),
            ];
        }

        foreach ($paycheckYears as $paycheckYear) {
            $year = $paycheckYear->year;
            $isPartialForPerson = $paycheckYear->paychecks->pluck('month')->unique()->count() < 12;

            foreach ($paycheckYear->paychecks as $paycheck) {
                $monthIndex = $paycheck->month - 1;

                $rowMap[$year]['cumulative_net'][$monthIndex] += MonthlyPortfolioSnapshot::toCents($paycheck->net);
                $rowMap[$year]['cumulative_gross'][$monthIndex] += MonthlyPortfolioSnapshot::toCents($paycheck->gross);
            }

            foreach ($paycheckYear->bonuses as $bonus) {
                $monthIndex = $this->bonusMonthIndex($bonus);

                $rowMap[$year]['cumulative_bonuses_gross'][$monthIndex] += MonthlyPortfolioSnapshot::toCents($bonus->amount);
                $rowMap[$year]['cumulative_bonuses_net'][$monthIndex] += $this->bonusNetInCents($bonus);
            }

            $rowMap[$year]['is_partial'] = $rowMap[$year]['is_partial'] || $isPartialForPerson;
            $rowMap[$year]['recorded_through_month'] = max(
                $rowMap[$year]['recorded_through_month'] ?? 0,
                $paycheckYear->paychecks->max('month') ?? 0,
            ) ?: null;

            if ($year === $currentYear) {
                $currentYearPeople[] = $paycheckYear->person_id;
            }
        }

        if (
            array_key_exists($currentYear, $rowMap)
            && count(array_unique($currentYearPeople)) < $expectedPeopleCount
        ) {
            $rowMap[$currentYear]['is_partial'] = true;
        }

        return array_values(array_map(fn (array $row): array => $this->finalizeRow($row), $rowMap));
    }

    /** @return array<int, int> */
    private function emptyMonthlyAmounts(): array
    {
        return array_fill(0, 12, 0);
    }

    /**
     * @param  array{
     *     year: int,
     *     is_partial: bool,
     *     recorded_through_month: int|null,
     *     net: string,
     *     gross: string,
     *     bonuses_gross: string,
     *     bonuses_net: string,
     *     gross_with_bonuses: string,
     *     net_with_bonuses: string,
     *     cumulative_net: array<int, int>,
     *     cumulative_gross: array<int, int>,
     *     cumulative_bonuses_gross: array<int, int>,
     *     cumulative_bonuses_net: array<int, int>
     * }  $row
     * @return array{
     *     year: int,
     *     is_partial: bool,
     *     recorded_through_month: int|null,
     *     net: string,
     *     gross: string,
     *     bonuses_gross: string,
     *     bonuses_net: string,
     *     gross_with_bonuses: string,
     *     net_with_bonuses: string,
     *     cumulative_net: array<int, string>,
     *     cumulative_gross: array<int, string>,
     *     cumulative_bonuses_gross: array<int, string>,
     *     cumulative_bonuses_net: array<int, string>
     * }
     */
    private function finalizeRow(array $row): array
    {
        $cumulativeNet = $this->toCumulativeAmounts($row['cumulative_net']);
        $cumulativeGross = $this->toCumulativeAmounts($row['cumulative_gross']);
        $cumulativeBonusesGross = $this->toCumulativeAmounts($row['cumulative_bonuses_gross']);
        $cumulativeBonusesNet = $this->toCumulativeAmounts($row['cumulative_bonuses_net']);

        return [
            'year' => $row['year'],
            'is_partial' => $row['is_partial'],
            'recorded_through_month' => $row['recorded_through_month'],
            'net' => $this->lastCumulativeAmount($cumulativeNet),
            'gross' => $this->lastCumulativeAmount($cumulativeGross),
            'bonuses_gross' => $this->lastCumulativeAmount($cumulativeBonusesGross),
            'bonuses_net' => $this->lastCumulativeAmount($cumulativeBonusesNet),
            'gross_with_bonuses' => MonthlyPortfolioSnapshot::fromCents(
                MonthlyPortfolioSnapshot::toCents($this->lastCumulativeAmount($cumulativeGross))
                    + MonthlyPortfolioSnapshot::toCents($this->lastCumulativeAmount($cumulativeBonusesGross)),
            ),
            'net_with_bonuses' => MonthlyPortfolioSnapshot::fromCents(
                MonthlyPortfolioSnapshot::toCents($this->lastCumulativeAmount($cumulativeNet))
                    + MonthlyPortfolioSnapshot::toCents($this->lastCumulativeAmount($cumulativeBonusesNet)),
            ),
            'cumulative_net' => $cumulativeNet,
            'cumulative_gross' => $cumulativeGross,
            'cumulative_bonuses_gross' => $cumulativeBonusesGross,
            'cumulative_bonuses_net' => $cumulativeBonusesNet,
        ];
    }

    /** @param  array<int, int>  $amounts */
    private function toCumulativeAmounts(array $amounts): array
    {
        $runningTotal = 0;

        return array_map(function (int $amount) use (&$runningTotal): string {
            $runningTotal += $amount;

            return MonthlyPortfolioSnapshot::fromCents($runningTotal);
        }, $amounts);
    }

    /** @param  array<int, string>  $cumulativeAmounts */
    private function lastCumulativeAmount(array $cumulativeAmounts): string
    {
        return $cumulativeAmounts[array_key_last($cumulativeAmounts)] ?? '0.00';
    }

    private function bonusMonthIndex(Bonus $bonus): int
    {
        if ($bonus->paid_at === null) {
            return 11;
        }

        return max(0, min(11, $bonus->paid_at->month - 1));
    }

    private function bonusNetInCents(Bonus $bonus): int
    {
        $amountInCents = MonthlyPortfolioSnapshot::toCents($bonus->amount);

        if (! $bonus->taxable) {
            return $amountInCents;
        }

        return $amountInCents - MonthlyPortfolioSnapshot::toCents($bonus->paid_tax);
    }

    /**
     * @param  array<int, array{
     *     year: int,
     *     is_partial: bool,
     *     recorded_through_month: int|null,
     *     net: string,
     *     gross: string,
     *     bonuses_gross: string,
     *     bonuses_net: string,
     *     gross_with_bonuses: string,
     *     net_with_bonuses: string,
     *     cumulative_net: array<int, string>,
     *     cumulative_gross: array<int, string>,
     *     cumulative_bonuses_gross: array<int, string>,
     *     cumulative_bonuses_net: array<int, string>
     * }>  $rows
     * @return array<int, array{key: string, label: string, color: string, values: array<int, float>}>
     */
    private function buildChartSeries(array $rows): array
    {
        $seriesMeta = [
            'net' => ['label' => 'Neto', 'color' => '#2563eb'],
            'gross' => ['label' => 'Bruto', 'color' => '#16a34a'],
            'net_with_bonuses' => ['label' => 'Neto z bonusi', 'color' => '#7c3aed'],
            'gross_with_bonuses' => ['label' => 'Bruto z bonusi', 'color' => '#ea580c'],
        ];

        return collect($seriesMeta)
            ->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'values' => array_map(fn (array $row): float => (float) $row[$key], $rows),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{
     *     year: int,
     *     is_partial: bool,
     *     recorded_through_month: int|null,
     *     net: string,
     *     gross: string,
     *     bonuses_gross: string,
     *     bonuses_net: string,
     *     gross_with_bonuses: string,
     *     net_with_bonuses: string,
     *     cumulative_net: array<int, string>,
     *     cumulative_gross: array<int, string>,
     *     cumulative_bonuses_gross: array<int, string>,
     *     cumulative_bonuses_net: array<int, string>
     * }>  $rows
     * @return array{
     *     latest_year: int|null,
     *     previous_year: int|null,
     *     net_change_amount: string|null,
     *     net_change_percentage: string|null,
     *     gross_change_amount: string|null,
     *     gross_change_percentage: string|null
     * }
     */
    private function buildSummary(array $rows): array
    {
        if ($rows === []) {
            return [
                'latest_year' => null,
                'previous_year' => null,
                'net_change_amount' => null,
                'net_change_percentage' => null,
                'gross_change_amount' => null,
                'gross_change_percentage' => null,
            ];
        }

        $latestRow = $rows[array_key_last($rows)];
        $previousRow = count($rows) > 1 ? $rows[array_key_last($rows) - 1] : null;
        $comparisonMonth = $latestRow['recorded_through_month'] ?? 12;
        $previousNet = $previousRow === null
            ? null
            : $this->cumulativeAmountAtMonth($previousRow['cumulative_net'], $comparisonMonth);
        $previousGross = $previousRow === null
            ? null
            : $this->cumulativeAmountAtMonth($previousRow['cumulative_gross'], $comparisonMonth);

        return [
            'latest_year' => $latestRow['year'],
            'previous_year' => $previousRow['year'] ?? null,
            'net_change_amount' => $previousNet === null
                ? null
                : MonthlyPortfolioSnapshot::fromCents(
                    MonthlyPortfolioSnapshot::toCents($latestRow['net'])
                        - MonthlyPortfolioSnapshot::toCents($previousNet),
                ),
            'net_change_percentage' => $this->percentageChange(
                current: $latestRow['net'],
                previous: $previousNet,
            ),
            'gross_change_amount' => $previousGross === null
                ? null
                : MonthlyPortfolioSnapshot::fromCents(
                    MonthlyPortfolioSnapshot::toCents($latestRow['gross'])
                        - MonthlyPortfolioSnapshot::toCents($previousGross),
                ),
            'gross_change_percentage' => $this->percentageChange(
                current: $latestRow['gross'],
                previous: $previousGross,
            ),
        ];
    }

    /** @param  array<int, string>  $cumulativeAmounts */
    private function cumulativeAmountAtMonth(array $cumulativeAmounts, int $month): string
    {
        $index = max(0, min(11, $month - 1));

        return $cumulativeAmounts[$index] ?? '0.00';
    }

    private function percentageChange(string $current, ?string $previous): ?string
    {
        $previousAmount = MonthlyPortfolioSnapshot::toCents($previous);

        if ($previous === null || $previousAmount === 0) {
            return null;
        }

        return number_format(
            ((MonthlyPortfolioSnapshot::toCents($current) - $previousAmount) / $previousAmount) * 100,
            2,
            '.',
            '',
        );
    }
}
