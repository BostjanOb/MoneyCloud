<?php

namespace App\Http\Requests;

use App\Models\InvestmentSymbol;
use Illuminate\Validation\Rule;

class UpdateInvestmentSymbolRequest extends StoreInvestmentSymbolRequest
{
    /** @return array<int, mixed> */
    protected function symbolRules(): array
    {
        $symbol = $this->route('investmentSymbol');

        if (! $symbol instanceof InvestmentSymbol) {
            return parent::symbolRules();
        }

        return [
            'required',
            'string',
            'max:50',
            Rule::unique('investment_symbols', 'symbol')
                ->ignore($symbol->id)
                ->where(fn ($query) => $query->where('type', $this->input('type'))),
        ];
    }

    /** @return array<int, mixed> */
    protected function isinRules(): array
    {
        $symbol = $this->route('investmentSymbol');

        if (! $symbol instanceof InvestmentSymbol) {
            return parent::isinRules();
        }

        return [
            'nullable',
            'string',
            'max:50',
            Rule::unique('investment_symbols', 'isin')->ignore($symbol->id),
        ];
    }
}
