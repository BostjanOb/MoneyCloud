<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentProvider;
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
}
