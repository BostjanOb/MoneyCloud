<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use App\Models\CryptoBalance;
use App\Models\InvestmentProvider;
use App\Models\InvestmentSymbol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCryptoBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'investment_provider_id' => [
                'required',
                'integer',
                Rule::exists('investment_providers', 'id'),
                Rule::unique('crypto_balances', 'investment_provider_id')
                    ->where(fn ($query) => $query->where('investment_symbol_id', $this->input('investment_symbol_id')))
                    ->ignore($this->currentBalance()?->id),
            ],
            'investment_symbol_id' => ['required', 'integer', Rule::exists('investment_symbols', 'id')],
            'manual_quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'investment_provider_id' => $this->filled('investment_provider_id')
                ? $this->integer('investment_provider_id')
                : null,
            'investment_symbol_id' => $this->filled('investment_symbol_id')
                ? $this->integer('investment_symbol_id')
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provider = $this->selectedProvider();
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
        });
    }

    protected function currentBalance(): ?CryptoBalance
    {
        $balance = $this->route('cryptoBalance');

        return $balance instanceof CryptoBalance ? $balance : null;
    }

    private function selectedProvider(): ?InvestmentProvider
    {
        if (! $this->filled('investment_provider_id')) {
            return null;
        }

        return InvestmentProvider::query()->find($this->integer('investment_provider_id'));
    }

    private function selectedSymbol(): ?InvestmentSymbol
    {
        if (! $this->filled('investment_symbol_id')) {
            return null;
        }

        return InvestmentSymbol::query()->find($this->integer('investment_symbol_id'));
    }
}
