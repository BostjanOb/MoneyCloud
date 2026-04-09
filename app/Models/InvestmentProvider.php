<?php

namespace App\Models;

use App\Enums\InvestmentProviderSlug;
use App\Enums\InvestmentSymbolType;
use Database\Factories\InvestmentProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'linked_savings_account_id', 'sort_order'])]
class InvestmentProvider extends Model
{
    /** @use HasFactory<InvestmentProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'slug' => InvestmentProviderSlug::class,
            'linked_savings_account_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<SavingsAccount, $this> */
    public function linkedSavingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'linked_savings_account_id');
    }

    /** @return HasMany<InvestmentPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(InvestmentPurchase::class)
            ->orderByDesc('purchased_at')
            ->orderByDesc('id');
    }

    /** @return list<InvestmentSymbolType> */
    public function supportedSymbolTypes(): array
    {
        return match ($this->slug) {
            InvestmentProviderSlug::IBKR => [
                InvestmentSymbolType::ETF,
                InvestmentSymbolType::STOCK,
                InvestmentSymbolType::CRYPTO,
            ],
            InvestmentProviderSlug::ILIRIKA => [
                InvestmentSymbolType::BOND,
            ],
        };
    }

    public function supportsSymbolType(InvestmentSymbolType $type): bool
    {
        return in_array($type, $this->supportedSymbolTypes(), true);
    }

    public function requiresLinkedSavingsAccount(): bool
    {
        return $this->slug === InvestmentProviderSlug::IBKR;
    }
}
