<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportCryptoDcaCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'symbol_ids' => ['required', 'array', 'min:1'],
            'symbol_ids.*' => [
                'integer',
                Rule::exists('investment_symbols', 'id')->where('type', InvestmentSymbolType::CRYPTO->value),
            ],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'symbol_ids.required' => 'Izberite vsaj en simbol.',
            'symbol_ids.min' => 'Izberite vsaj en simbol.',
            'symbol_ids.*.exists' => 'Izbran simbol ni kripto simbol.',
            'from.date_format' => 'Datum „od“ ni veljaven.',
            'to.date_format' => 'Datum „do“ ni veljaven.',
            'to.after_or_equal' => 'Datum „do“ mora biti enak ali kasnejši od datuma „od“.',
        ];
    }

    /** @return list<int> */
    public function symbolIds(): array
    {
        return array_values(array_map('intval', $this->validated('symbol_ids')));
    }

    public function from(): ?CarbonImmutable
    {
        $from = $this->validated('from');

        return $from === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $from)->startOfDay();
    }

    public function to(): ?CarbonImmutable
    {
        $to = $this->validated('to');

        return $to === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $to)->endOfDay();
    }
}
