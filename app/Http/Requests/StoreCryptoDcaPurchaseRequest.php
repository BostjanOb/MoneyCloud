<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use App\Enums\InvestmentTransactionType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCryptoDcaPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'investment_provider_id' => ['required', 'integer', Rule::exists('investment_providers', 'id')],
            'investment_symbol_id' => ['required', 'integer', Rule::exists('investment_symbols', 'id')],
            'purchased_at' => ['required', 'date'],
            'transaction_type' => ['required', Rule::in(array_column(InvestmentTransactionType::cases(), 'value'))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'fee' => ['required', 'numeric', 'min:0'],
            'add_to_balance' => ['sometimes', 'boolean'],
            'balance_provider_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->boolean('add_to_balance')),
                Rule::exists('investment_providers', 'id'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'add_to_balance' => $this->boolean('add_to_balance'),
            'investment_provider_id' => $this->filled('investment_provider_id')
                ? $this->integer('investment_provider_id')
                : null,
            'investment_symbol_id' => $this->filled('investment_symbol_id')
                ? $this->integer('investment_symbol_id')
                : null,
            'transaction_type' => $this->input('transaction_type', InvestmentTransactionType::Buy->value),
            'balance_provider_id' => $this->filled('balance_provider_id')
                ? $this->integer('balance_provider_id')
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function purchaseAttributes(): array
    {
        $validated = $this->validated();

        return [
            'investment_provider_id' => $validated['investment_provider_id'],
            'investment_symbol_id' => $validated['investment_symbol_id'],
            'purchased_at' => $validated['purchased_at'],
            'transaction_type' => $validated['transaction_type'],
            'quantity' => $validated['quantity'],
            'price_per_unit' => $validated['price_per_unit'],
            'fee' => $validated['fee'],
        ];
    }

    public function shouldAddToBalance(): bool
    {
        $validated = $this->validated();

        return (bool) ($validated['add_to_balance'] ?? false);
    }

    public function balanceProviderId(): ?int
    {
        $validated = $this->validated();
        $balanceProviderId = $validated['balance_provider_id'] ?? null;

        return $balanceProviderId === null ? null : (int) $balanceProviderId;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provider = $this->selectedProvider();
            $balanceProvider = $this->selectedBalanceProvider();
            $symbol = $this->selectedSymbol();

            if ($provider !== null && ! $provider->supportsCrypto()) {
                $validator->errors()->add(
                    'investment_provider_id',
                    'Izbrana platforma ne podpira kripta.',
                );
            }

            if ($symbol !== null && $symbol->type !== InvestmentSymbolType::CRYPTO) {
                $validator->errors()->add(
                    'investment_symbol_id',
                    'Izbrani simbol ni kripto simbol.',
                );
            }

            if (
                $this->boolean('add_to_balance')
                && $balanceProvider !== null
                && ! $balanceProvider->supportsCrypto()
            ) {
                $validator->errors()->add(
                    'balance_provider_id',
                    'Izbrana platforma za stanje ne podpira kripta.',
                );
            }

            if ($symbol !== null && $this->wouldMakeNetQuantityNegative()) {
                $validator->errors()->add(
                    'quantity',
                    'Prodaja presega trenutno količino za izbrani simbol.',
                );
            }

            if (
                $this->boolean('add_to_balance')
                && $balanceProvider !== null
                && $symbol !== null
                && $this->transactionType() === InvestmentTransactionType::Sell
                && $this->wouldMakeManualBalanceNegative($balanceProvider->id, $symbol->id)
            ) {
                $validator->errors()->add(
                    'quantity',
                    'Prodaja presega trenutno stanje na izbrani platformi.',
                );
            }
        });
    }

    private function selectedProvider(): ?InvestmentProvider
    {
        if (! $this->filled('investment_provider_id')) {
            return null;
        }

        return InvestmentProvider::query()->find($this->integer('investment_provider_id'));
    }

    private function selectedBalanceProvider(): ?InvestmentProvider
    {
        if (! $this->filled('balance_provider_id')) {
            return null;
        }

        return InvestmentProvider::query()->find($this->integer('balance_provider_id'));
    }

    private function selectedSymbol(): ?InvestmentSymbol
    {
        if (! $this->filled('investment_symbol_id')) {
            return null;
        }

        return InvestmentSymbol::query()->find($this->integer('investment_symbol_id'));
    }

    private function currentPurchase(): ?InvestmentPurchase
    {
        $purchase = $this->route('investmentPurchase');

        return $purchase instanceof InvestmentPurchase ? $purchase : null;
    }

    private function transactionType(): InvestmentTransactionType
    {
        return InvestmentTransactionType::tryFrom(
            (string) $this->input('transaction_type', InvestmentTransactionType::Buy->value),
        ) ?? InvestmentTransactionType::Buy;
    }

    private function wouldMakeNetQuantityNegative(): bool
    {
        if (! $this->filled('investment_symbol_id') || ! $this->filled('quantity')) {
            return false;
        }

        $purchase = $this->currentPurchase();
        $netQuantity = InvestmentPurchase::query()
            ->where('investment_symbol_id', $this->integer('investment_symbol_id'))
            ->whereHas(
                'provider',
                fn ($query) => $query->whereJsonContains(
                    'supported_symbol_types',
                    InvestmentSymbolType::CRYPTO->value,
                ),
            )
            ->whereHas(
                'symbol',
                fn ($query) => $query->where('type', InvestmentSymbolType::CRYPTO->value),
            )
            ->when(
                $purchase instanceof InvestmentPurchase,
                fn ($query) => $query->whereKeyNot($purchase->id),
            )
            ->get()
            ->sum(fn (InvestmentPurchase $row): float => $row->signedQuantity());

        $submittedQuantity = ((float) $this->input('quantity')) * $this->transactionType()->multiplier();

        return ($netQuantity + $submittedQuantity) < 0;
    }

    private function wouldMakeManualBalanceNegative(int $providerId, int $symbolId): bool
    {
        $manualQuantity = (float) CryptoBalance::query()
            ->where('investment_provider_id', $providerId)
            ->where('investment_symbol_id', $symbolId)
            ->value('manual_quantity');

        return ($manualQuantity - (float) $this->input('quantity')) < 0;
    }
}
