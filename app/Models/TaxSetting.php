<?php

namespace App\Models;

use Database\Factories\TaxSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['year_from', 'year_to', 'general_relief_brackets', 'child_relief1', 'child_relief2', 'child_relief3', 'brackets'])]
class TaxSetting extends Model
{
    /** @use HasFactory<TaxSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
            'general_relief_brackets' => 'array',
            'child_relief1' => 'decimal:2',
            'child_relief2' => 'decimal:2',
            'child_relief3' => 'decimal:2',
            'brackets' => 'array',
        ];
    }

    /**
     * @return array<int, array{
     *     bracket_from: float|int|string,
     *     bracket_to: float|int|string|null,
     *     base_tax: float|int|string,
     *     rate: float|int|string
     * }>
     */
    /**
     * @return array<int, array{
     *     income_from: float|int|string,
     *     income_to: float|int|string|null,
     *     base_relief: float|int|string,
     *     formula_constant: float|int|string|null,
     *     formula_multiplier: float|int|string|null
     * }>
     */
    public function orderedGeneralReliefBrackets(): array
    {
        return collect($this->general_relief_brackets ?? [])
            ->sortBy('income_from', SORT_NUMERIC)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     bracket_from: float|int|string,
     *     bracket_to: float|int|string|null,
     *     base_tax: float|int|string,
     *     rate: float|int|string
     * }>
     */
    public function orderedBrackets(): array
    {
        return collect($this->brackets ?? [])
            ->sortBy('bracket_from', SORT_NUMERIC)
            ->values()
            ->all();
    }

    public static function findForYear(int $year): ?self
    {
        return self::where('year_from', '<=', $year)
            ->where(fn ($q) => $q->where('year_to', '>=', $year)->orWhereNull('year_to'))
            ->first();
    }
}
