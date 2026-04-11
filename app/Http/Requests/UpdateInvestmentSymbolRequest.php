<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentSymbol;
use Illuminate\Validation\Rule;

class UpdateInvestmentSymbolRequest extends StoreInvestmentSymbolRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $symbol = $this->route('investmentSymbol');

        if (! $symbol instanceof InvestmentSymbol) {
            return parent::rules();
        }

        return [
            'type' => ['required', Rule::enum(InvestmentSymbolType::class)],
            'symbol' => [
                'required',
                'string',
                'max:50',
                Rule::unique('investment_symbols', 'symbol')
                    ->ignore($symbol->id)
                    ->where(fn ($query) => $query->where('type', $this->input('type'))),
            ],
            'isin' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('investment_symbols', 'isin')->ignore($symbol->id),
            ],
            'taxable' => ['required', 'boolean'],
            'price_source' => ['required', 'string', 'max:255'],
            'coinmarketcap_id' => ['nullable', 'integer', 'min:1'],
            'yfapi_symbol' => ['nullable', 'string', 'max:50'],
            'current_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
