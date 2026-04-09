<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentSymbolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(InvestmentSymbolType::class)],
            'symbol' => [
                'required',
                'string',
                'max:50',
                Rule::unique('investment_symbols', 'symbol')->where(
                    fn ($query) => $query->where('type', $this->input('type')),
                ),
            ],
            'isin' => ['nullable', 'string', 'max:50', 'unique:investment_symbols,isin'],
            'taxable' => ['required', 'boolean'],
            'price_source' => ['required', 'string', 'max:255'],
            'current_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'symbol' => mb_strtoupper((string) $this->input('symbol')),
            'isin' => $this->filled('isin') ? mb_strtoupper((string) $this->input('isin')) : null,
            'taxable' => $this->boolean('taxable'),
        ]);
    }
}
