<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentProvider;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentSymbol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvestmentPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'investment_symbol_id' => ['required', 'integer', Rule::exists('investment_symbols', 'id')],
            'purchased_at' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'fee' => ['required', 'numeric', 'min:0'],
            'yield' => ['nullable', 'numeric', 'min:0'],
            'coupon_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'yield' => $this->filled('yield') ? $this->input('yield') : null,
            'coupon_date' => $this->filled('coupon_date') ? $this->input('coupon_date') : null,
            'expiry_date' => $this->filled('expiry_date') ? $this->input('expiry_date') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provider = $this->currentProvider();
            $symbol = $this->selectedSymbol();

            if ($provider === null || $symbol === null) {
                return;
            }

            if (! $provider->supportsSymbolType($symbol->type)) {
                $validator->errors()->add(
                    'investment_symbol_id',
                    'Izbrani simbol ni na voljo za tega ponudnika.',
                );
            }

            if ($symbol->type === InvestmentSymbolType::BOND) {
                if (! $this->filled('yield')) {
                    $validator->errors()->add('yield', 'Donos je obvezen za obveznice.');
                }

                if (! $this->filled('coupon_date')) {
                    $validator->errors()->add('coupon_date', 'Datum kupona je obvezen za obveznice.');
                }

                if (! $this->filled('expiry_date')) {
                    $validator->errors()->add('expiry_date', 'Datum zapadlosti je obvezen za obveznice.');
                }

                if (
                    $this->filled('coupon_date')
                    && $this->filled('expiry_date')
                    && (string) $this->input('expiry_date') < (string) $this->input('coupon_date')
                ) {
                    $validator->errors()->add(
                        'expiry_date',
                        'Datum zapadlosti mora biti na ali po datumu kupona.',
                    );
                }

                return;
            }

            foreach (['yield', 'coupon_date', 'expiry_date'] as $field) {
                if ($this->filled($field)) {
                    $validator->errors()->add(
                        $field,
                        'To polje je dovoljeno samo pri obveznicah.',
                    );
                }
            }
        });
    }

    protected function currentProvider(): ?InvestmentProvider
    {
        $provider = $this->route('investmentProvider');

        if ($provider instanceof InvestmentProvider) {
            return $provider;
        }

        $purchase = $this->route('investmentPurchase');

        return $purchase instanceof InvestmentPurchase ? $purchase->provider : null;
    }

    protected function selectedSymbol(): ?InvestmentSymbol
    {
        if (! $this->filled('investment_symbol_id')) {
            return null;
        }

        return InvestmentSymbol::query()->find($this->integer('investment_symbol_id'));
    }
}
