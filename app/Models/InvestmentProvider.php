<?php

namespace App\Models;

use App\Enums\InvestmentSymbolType;
use Database\Factories\InvestmentProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'linked_savings_account_id',
    'requires_linked_savings_account',
    'supported_symbol_types',
    'sort_order',
])]
class InvestmentProvider extends Model
{
    /** @use HasFactory<InvestmentProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'linked_savings_account_id' => 'integer',
            'requires_linked_savings_account' => 'boolean',
            'supported_symbol_types' => 'array',
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

    /** @return HasMany<CryptoBalance, $this> */
    public function cryptoBalances(): HasMany
    {
        return $this->hasMany(CryptoBalance::class);
    }

    /** @return list<InvestmentSymbolType> */
    public function supportedSymbolTypes(): array
    {
        $symbolTypes = [];

        foreach ($this->supported_symbol_types ?? [] as $type) {
            if (! is_string($type)) {
                continue;
            }

            $symbolType = InvestmentSymbolType::tryFrom($type);

            if ($symbolType !== null) {
                $symbolTypes[] = $symbolType;
            }
        }

        return $symbolTypes;
    }

    public function supportsSymbolType(InvestmentSymbolType $type): bool
    {
        return in_array($type->value, $this->supported_symbol_types ?? [], true);
    }

    public function supportsCrypto(): bool
    {
        return $this->supportsSymbolType(InvestmentSymbolType::CRYPTO);
    }

    public function supportsNonCrypto(): bool
    {
        foreach ($this->supportedSymbolTypes() as $symbolType) {
            if ($symbolType !== InvestmentSymbolType::CRYPTO) {
                return true;
            }
        }

        return false;
    }

    public function requiresLinkedSavingsAccount(): bool
    {
        return $this->requires_linked_savings_account;
    }
}
