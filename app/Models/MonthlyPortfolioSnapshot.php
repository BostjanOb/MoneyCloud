<?php

namespace App\Models;

use Database\Factories\MonthlyPortfolioSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'month_date',
    'savings_amount',
    'bond_amount',
    'etf_amount',
    'crypto_amount',
    'stock_amount',
    'total_amount',
    'source',
])]
class MonthlyPortfolioSnapshot extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SCHEDULED = 'scheduled';

    /** @use HasFactory<MonthlyPortfolioSnapshotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'month_date' => 'date',
            'savings_amount' => 'decimal:2',
            'bond_amount' => 'decimal:2',
            'etf_amount' => 'decimal:2',
            'crypto_amount' => 'decimal:2',
            'stock_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @param Builder<MonthlyPortfolioSnapshot> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('month_date')->orderBy('id');
    }

    public static function toCents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public static function fromCents(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, '.', '');
    }
}
