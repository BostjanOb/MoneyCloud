<?php

namespace App\Services;

use App\Models\Bonus;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use App\Models\TaxSetting;
use Illuminate\Support\Collection;

class TaxCalculationService
{
    /**
     * @return array{
     *     sum_gross: string,
     *     sum_net: string,
     *     sum_contributions: string,
     *     sum_taxes: string,
     *     osnova: string,
     *     olajsave: string,
     *     davcna_osnova: string,
     *     dohodnina: string,
     *     razlika: string,
     *     has_tax_settings: bool,
     *     breakdown: array<string, string>,
     *     projection: array{
     *         months_used: int,
     *         is_final: bool,
     *         sum_gross: string,
     *         sum_net: string,
     *         sum_contributions: string,
     *         sum_taxes: string,
     *         osnova: string,
     *         olajsave: string,
     *         davcna_osnova: string,
     *         dohodnina: string,
     *         razlika: string
     *     },
     * }
     */
    public function calculate(PaycheckYear $paycheckYear): array
    {
        $paycheckYear->loadMissing(['paychecks', 'bonuses']);

        $paycheckSummary = $this->summarizePaychecks($paycheckYear->paychecks);
        $bonusSummary = $this->summarizeTaxableBonuses($paycheckYear->bonuses);
        $actualSummary = $this->mergeIncomeSummaries($paycheckSummary, $bonusSummary);

        $taxSetting = TaxSetting::findForYear($paycheckYear->year);

        if (! $taxSetting) {
            return [
                'sum_gross' => $this->formatAmount($actualSummary['sum_gross']),
                'sum_net' => $this->formatAmount($actualSummary['sum_net']),
                'sum_contributions' => $this->formatAmount($actualSummary['sum_contributions']),
                'sum_taxes' => $this->formatAmount($actualSummary['sum_taxes']),
                'osnova' => '0.00',
                'olajsave' => '0.00',
                'davcna_osnova' => '0.00',
                'dohodnina' => '0.00',
                'razlika' => '0.00',
                'has_tax_settings' => false,
                'breakdown' => [],
                'projection' => $this->emptyProjection(),
            ];
        }

        $childReliefs = $this->calculateChildReliefs($paycheckYear, $taxSetting);
        $generalRelief = $this->calculateGeneralRelief($actualSummary['sum_gross'], $taxSetting);
        $olajsave = $generalRelief + $childReliefs['total'];

        $actualCalculation = $this->calculateAmounts(
            gross: $actualSummary['sum_gross'],
            contributions: $actualSummary['sum_contributions'],
            taxes: $actualSummary['sum_taxes'],
            olajsave: $olajsave,
            taxSetting: $taxSetting,
        );

        $projection = $this->buildProjection($paycheckYear->paychecks, $paycheckYear->bonuses, $childReliefs, $taxSetting);

        return [
            'sum_gross' => $this->formatAmount($actualSummary['sum_gross']),
            'sum_net' => $this->formatAmount($actualSummary['sum_net']),
            'sum_contributions' => $this->formatAmount($actualSummary['sum_contributions']),
            'sum_taxes' => $this->formatAmount($actualSummary['sum_taxes']),
            'osnova' => $this->formatAmount($actualCalculation['osnova']),
            'olajsave' => $this->formatAmount($olajsave),
            'davcna_osnova' => $this->formatAmount($actualCalculation['davcna_osnova']),
            'dohodnina' => $this->formatAmount($actualCalculation['dohodnina']),
            'razlika' => $this->formatAmount($actualCalculation['razlika']),
            'has_tax_settings' => true,
            'breakdown' => [
                'general_relief' => $this->formatAmount($generalRelief),
                'child_relief1' => $this->formatAmount($childReliefs['child_relief1']),
                'child_relief2' => $this->formatAmount($childReliefs['child_relief2']),
                'child_relief3' => $this->formatAmount($childReliefs['child_relief3']),
            ],
            'projection' => $projection,
        ];
    }

    private function calculateTax(float $davcnaOsnova, TaxSetting $taxSetting): float
    {
        if ($davcnaOsnova <= 0) {
            return 0;
        }

        foreach ($taxSetting->orderedBrackets() as $bracket) {
            $bracketTo = $bracket['bracket_to'] !== null ? (float) $bracket['bracket_to'] : PHP_FLOAT_MAX;

            if ($davcnaOsnova > (float) $bracket['bracket_from'] && $davcnaOsnova <= $bracketTo) {
                return (float) $bracket['base_tax']
                    + ($davcnaOsnova - (float) $bracket['bracket_from']) * (float) $bracket['rate'] / 100;
            }
        }

        return 0;
    }

    /**
     * @return array{child_relief1: float, child_relief2: float, child_relief3: float, total: float}
     */
    private function calculateChildReliefs(PaycheckYear $paycheckYear, TaxSetting $taxSetting): array
    {
        $childRelief1 = (float) $taxSetting->child_relief1 / 12 * $paycheckYear->child1_months;
        $childRelief2 = (float) $taxSetting->child_relief2 / 12 * $paycheckYear->child2_months;
        $childRelief3 = (float) $taxSetting->child_relief3 / 12 * $paycheckYear->child3_months;

        return [
            'child_relief1' => $childRelief1,
            'child_relief2' => $childRelief2,
            'child_relief3' => $childRelief3,
            'total' => $childRelief1 + $childRelief2 + $childRelief3,
        ];
    }

    private function calculateGeneralRelief(float $sumGross, TaxSetting $taxSetting): float
    {
        foreach ($taxSetting->orderedGeneralReliefBrackets() as $bracket) {
            $incomeFrom = (float) $bracket['income_from'];
            $incomeTo = $bracket['income_to'] !== null ? (float) $bracket['income_to'] : null;

            if (! $this->matchesIncomeBracket($sumGross, $incomeFrom, $incomeTo)) {
                continue;
            }

            return $this->resolveGeneralReliefBracketAmount($bracket, $sumGross);
        }

        return 0;
    }

    private function matchesIncomeBracket(float $sumGross, float $incomeFrom, ?float $incomeTo): bool
    {
        $matchesLowerBound = $incomeFrom === 0.0 ? $sumGross >= 0 : $sumGross > $incomeFrom;
        $matchesUpperBound = $incomeTo === null || $sumGross <= $incomeTo;

        return $matchesLowerBound && $matchesUpperBound;
    }

    /**
     * @param  array{
     *     income_from: float|int|string,
     *     income_to: float|int|string|null,
     *     base_relief: float|int|string,
     *     formula_constant: float|int|string|null,
     *     formula_multiplier: float|int|string|null
     * }  $bracket
     */
    private function resolveGeneralReliefBracketAmount(array $bracket, float $sumGross): float
    {
        $baseRelief = (float) $bracket['base_relief'];
        $formulaConstant = $bracket['formula_constant'];
        $formulaMultiplier = $bracket['formula_multiplier'];

        if ($formulaConstant === null || $formulaMultiplier === null) {
            return $baseRelief;
        }

        return $baseRelief + ((float) $formulaConstant - (float) $formulaMultiplier * $sumGross);
    }

    /**
     * @param  Collection<int, Paycheck>  $paychecks
     * @return array{sum_gross: float, sum_net: float, sum_contributions: float, sum_taxes: float}
     */
    private function summarizePaychecks(Collection $paychecks): array
    {
        return [
            'sum_gross' => (float) $paychecks->sum('gross'),
            'sum_net' => (float) $paychecks->sum('net'),
            'sum_contributions' => (float) $paychecks->sum('contributions'),
            'sum_taxes' => (float) $paychecks->sum('taxes'),
        ];
    }

    /**
     * @param  Collection<int, Bonus>  $bonuses
     * @return array{sum_gross: float, sum_taxes: float}
     */
    private function summarizeTaxableBonuses(Collection $bonuses): array
    {
        $taxableBonuses = $bonuses->where('taxable', true);

        return [
            'sum_gross' => (float) $taxableBonuses->sum('amount'),
            'sum_taxes' => (float) $taxableBonuses->sum('paid_tax'),
        ];
    }

    /**
     * @param  array{sum_gross: float, sum_net: float, sum_contributions: float, sum_taxes: float}  $paycheckSummary
     * @param  array{sum_gross: float, sum_taxes: float}  $bonusSummary
     * @return array{sum_gross: float, sum_net: float, sum_contributions: float, sum_taxes: float}
     */
    private function mergeIncomeSummaries(array $paycheckSummary, array $bonusSummary): array
    {
        return [
            'sum_gross' => $paycheckSummary['sum_gross'] + $bonusSummary['sum_gross'],
            'sum_net' => $paycheckSummary['sum_net'],
            'sum_contributions' => $paycheckSummary['sum_contributions'],
            'sum_taxes' => $paycheckSummary['sum_taxes'] + $bonusSummary['sum_taxes'],
        ];
    }

    /**
     * @param  Collection<int, Paycheck>  $paychecks
     * @param  Collection<int, Bonus>  $bonuses
     * @return array{
     *     months_used: int,
     *     is_final: bool,
     *     sum_gross: string,
     *     sum_net: string,
     *     sum_contributions: string,
     *     sum_taxes: string,
     *     osnova: string,
     *     olajsave: string,
     *     davcna_osnova: string,
     *     dohodnina: string,
     *     razlika: string
     * }
     */
    private function buildProjection(
        Collection $paychecks,
        Collection $bonuses,
        array $childReliefs,
        TaxSetting $taxSetting,
    ): array {
        $monthsEntered = $paychecks
            ->pluck('month')
            ->unique()
            ->count();
        $bonusSummary = $this->summarizeTaxableBonuses($bonuses);

        if ($monthsEntered === 12) {
            $yearSummary = $this->mergeIncomeSummaries($this->summarizePaychecks($paychecks), $bonusSummary);
            $generalRelief = $this->calculateGeneralRelief($yearSummary['sum_gross'], $taxSetting);
            $olajsave = $generalRelief + $childReliefs['total'];
            $finalCalculation = $this->calculateAmounts(
                gross: $yearSummary['sum_gross'],
                contributions: $yearSummary['sum_contributions'],
                taxes: $yearSummary['sum_taxes'],
                olajsave: $olajsave,
                taxSetting: $taxSetting,
            );

            return [
                'months_used' => 12,
                'is_final' => true,
                'sum_gross' => $this->formatAmount($yearSummary['sum_gross']),
                'sum_net' => $this->formatAmount($yearSummary['sum_net']),
                'sum_contributions' => $this->formatAmount($yearSummary['sum_contributions']),
                'sum_taxes' => $this->formatAmount($yearSummary['sum_taxes']),
                'osnova' => $this->formatAmount($finalCalculation['osnova']),
                'olajsave' => $this->formatAmount($olajsave),
                'davcna_osnova' => $this->formatAmount($finalCalculation['davcna_osnova']),
                'dohodnina' => $this->formatAmount($finalCalculation['dohodnina']),
                'razlika' => $this->formatAmount($finalCalculation['razlika']),
            ];
        }

        $recentPaychecks = $paychecks
            ->sortByDesc('month')
            ->take(3)
            ->values();

        $monthsUsed = $recentPaychecks->count();

        if ($monthsUsed === 0) {
            return $this->emptyProjection();
        }

        $recentSummary = $this->summarizePaychecks($recentPaychecks);

        $projectedGross = $recentSummary['sum_gross'] / $monthsUsed * 12 + $bonusSummary['sum_gross'];
        $projectedNet = $recentSummary['sum_net'] / $monthsUsed * 12;
        $projectedContributions = $recentSummary['sum_contributions'] / $monthsUsed * 12;
        $projectedTaxes = $recentSummary['sum_taxes'] / $monthsUsed * 12 + $bonusSummary['sum_taxes'];
        $generalRelief = $this->calculateGeneralRelief($projectedGross, $taxSetting);
        $olajsave = $generalRelief + $childReliefs['total'];

        $projectionCalculation = $this->calculateAmounts(
            gross: $projectedGross,
            contributions: $projectedContributions,
            taxes: $projectedTaxes,
            olajsave: $olajsave,
            taxSetting: $taxSetting,
        );

        return [
            'months_used' => $monthsUsed,
            'is_final' => false,
            'sum_gross' => $this->formatAmount($projectedGross),
            'sum_net' => $this->formatAmount($projectedNet),
            'sum_contributions' => $this->formatAmount($projectedContributions),
            'sum_taxes' => $this->formatAmount($projectedTaxes),
            'osnova' => $this->formatAmount($projectionCalculation['osnova']),
            'olajsave' => $this->formatAmount($olajsave),
            'davcna_osnova' => $this->formatAmount($projectionCalculation['davcna_osnova']),
            'dohodnina' => $this->formatAmount($projectionCalculation['dohodnina']),
            'razlika' => $this->formatAmount($projectionCalculation['razlika']),
        ];
    }

    /**
     * @return array{osnova: float, davcna_osnova: float, dohodnina: float, razlika: float}
     */
    private function calculateAmounts(
        float $gross,
        float $contributions,
        float $taxes,
        float $olajsave,
        TaxSetting $taxSetting,
    ): array {
        $osnova = $gross - $contributions;
        $davcnaOsnova = max(0, $osnova - $olajsave);
        $dohodnina = $this->calculateTax($davcnaOsnova, $taxSetting);

        return [
            'osnova' => $osnova,
            'davcna_osnova' => $davcnaOsnova,
            'dohodnina' => $dohodnina,
            'razlika' => $dohodnina - $taxes,
        ];
    }

    /**
     * @return array{
     *     months_used: int,
     *     is_final: bool,
     *     sum_gross: string,
     *     sum_net: string,
     *     sum_contributions: string,
     *     sum_taxes: string,
     *     osnova: string,
     *     olajsave: string,
     *     davcna_osnova: string,
     *     dohodnina: string,
     *     razlika: string
     * }
     */
    private function emptyProjection(): array
    {
        return [
            'months_used' => 0,
            'is_final' => false,
            'sum_gross' => '0.00',
            'sum_net' => '0.00',
            'sum_contributions' => '0.00',
            'sum_taxes' => '0.00',
            'osnova' => '0.00',
            'olajsave' => '0.00',
            'davcna_osnova' => '0.00',
            'dohodnina' => '0.00',
            'razlika' => '0.00',
        ];
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
