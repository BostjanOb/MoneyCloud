<?php

namespace App\Models;

use App\Enums\InvestmentTransactionType;
use Database\Factories\InvestmentPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_provider_id',
    'investment_symbol_id',
    'purchased_at',
    'transaction_type',
    'quantity',
    'price_per_unit',
    'fee',
    'yield',
    'coupon_date',
    'expiry_date',
])]
class InvestmentPurchase extends Model
{
    /** @use HasFactory<InvestmentPurchaseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'investment_provider_id' => 'integer',
            'investment_symbol_id' => 'integer',
            'purchased_at' => 'datetime',
            'transaction_type' => InvestmentTransactionType::class,
            'quantity' => 'decimal:8',
            'price_per_unit' => 'decimal:2',
            'fee' => 'decimal:2',
            'yield' => 'decimal:2',
            'coupon_date' => 'date',
            'expiry_date' => 'date',
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

    public function transactionType(): InvestmentTransactionType
    {
        return $this->transaction_type instanceof InvestmentTransactionType
            ? $this->transaction_type
            : InvestmentTransactionType::Buy;
    }

    public function signedQuantity(): float
    {
        return ((float) $this->quantity) * $this->transactionType()->multiplier();
    }
}
