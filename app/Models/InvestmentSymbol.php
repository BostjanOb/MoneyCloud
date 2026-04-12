<?php

namespace App\Models;

use App\Enums\InvestmentSymbolType;
use Database\Factories\InvestmentSymbolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'symbol', 'isin', 'taxable', 'price_source', 'external_source_id', 'current_price', 'price_synced_at'])]
class InvestmentSymbol extends Model
{
    /** @use HasFactory<InvestmentSymbolFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => InvestmentSymbolType::class,
            'taxable' => 'boolean',
            'current_price' => 'decimal:2',
            'price_synced_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<InvestmentPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(InvestmentPurchase::class);
    }

    /** @return HasMany<CryptoBalance, $this> */
    public function cryptoBalances(): HasMany
    {
        return $this->hasMany(CryptoBalance::class);
    }
}
