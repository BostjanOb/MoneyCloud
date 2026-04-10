<?php

namespace App\Models;

use Database\Factories\CryptoBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['investment_provider_id', 'investment_symbol_id', 'manual_quantity'])]
class CryptoBalance extends Model
{
    /** @use HasFactory<CryptoBalanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'investment_provider_id' => 'integer',
            'investment_symbol_id' => 'integer',
            'manual_quantity' => 'decimal:8',
        ];
    }

    /** @return BelongsTo<InvestmentProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(InvestmentProvider::class, 'investment_provider_id');
    }

    /** @return BelongsTo<InvestmentSymbol, $this> */
    public function symbol(): BelongsTo
    {
        return $this->belongsTo(InvestmentSymbol::class, 'investment_symbol_id');
    }
}
