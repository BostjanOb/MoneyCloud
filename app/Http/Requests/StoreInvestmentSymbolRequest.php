<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentSymbolRequest extends FormRequest
{
    private const COINMARKETCAP_PRICE_SOURCE = 'coinmarketcap';

    private const YFAPI_PRICE_SOURCE = 'yfapi';

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
            'coinmarketcap_id' => ['nullable', 'integer', 'min:1'],
            'yfapi_symbol' => ['nullable', 'string', 'max:50'],
            'current_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('type');

        $coinmarketcapId = $type === InvestmentSymbolType::CRYPTO->value && $this->filled('coinmarketcap_id')
            ? $this->integer('coinmarketcap_id')
            : null;

        $yfapiSymbol = $type !== InvestmentSymbolType::CRYPTO->value && $this->filled('yfapi_symbol')
            ? mb_strtoupper(trim((string) $this->input('yfapi_symbol')))
            : null;

        $priceSource = match (true) {
            $coinmarketcapId !== null => self::COINMARKETCAP_PRICE_SOURCE,
            $yfapiSymbol !== null => self::YFAPI_PRICE_SOURCE,
            default => (string) $this->input('price_source'),
        };

        $this->merge([
            'symbol' => mb_strtoupper((string) $this->input('symbol')),
            'isin' => $this->filled('isin') ? mb_strtoupper((string) $this->input('isin')) : null,
            'taxable' => $this->boolean('taxable'),
            'price_source' => $priceSource,
            'coinmarketcap_id' => $coinmarketcapId,
            'yfapi_symbol' => $yfapiSymbol,
        ]);
    }
}
