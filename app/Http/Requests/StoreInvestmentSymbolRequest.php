<?php

namespace App\Http\Requests;

use App\Enums\InvestmentPriceSource;
use App\Enums\InvestmentSymbolType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'symbol' => $this->symbolRules(),
            'isin' => $this->isinRules(),
            'taxable' => ['required', 'boolean'],
            'price_source' => ['required', Rule::enum(InvestmentPriceSource::class)],
            'external_source_id' => ['nullable', 'string', 'max:255'],
            'current_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /** @return array<int, mixed> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->selectedType();
                $priceSource = $this->selectedPriceSource();
                $externalSourceId = $this->normalizedExternalSourceId();

                if (! $type instanceof InvestmentSymbolType || ! $priceSource instanceof InvestmentPriceSource) {
                    return;
                }

                if (! $priceSource->supportsType($type)) {
                    $validator->errors()->add('price_source', 'Izbrani vir cene ni na voljo za ta tip simbola.');

                    return;
                }

                if ($priceSource === InvestmentPriceSource::MANUAL) {
                    return;
                }

                if ($externalSourceId === null) {
                    $validator->errors()->add('external_source_id', 'Zunanji ID je obvezen za izbrani vir cene.');

                    return;
                }

                if (
                    $priceSource === InvestmentPriceSource::COINMARKETCAP
                    && ! ctype_digit($externalSourceId)
                ) {
                    $validator->errors()->add('external_source_id', 'CoinMarketCap ID mora biti številka.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $priceSource = $this->selectedPriceSource();

        $this->merge([
            'symbol' => mb_strtoupper(trim((string) $this->input('symbol'))),
            'isin' => $this->filled('isin') ? mb_strtoupper(trim((string) $this->input('isin'))) : null,
            'taxable' => $this->boolean('taxable'),
            'price_source' => $priceSource?->value ?? mb_strtolower(trim((string) $this->input('price_source'))),
            'external_source_id' => $this->normalizedExternalSourceId($priceSource),
        ]);
    }

    /** @return array<int, mixed> */
    protected function symbolRules(): array
    {
        return [
            'required',
            'string',
            'max:50',
            Rule::unique('investment_symbols', 'symbol')->where(
                fn ($query) => $query->where('type', $this->input('type')),
            ),
        ];
    }

    /** @return array<int, mixed> */
    protected function isinRules(): array
    {
        return ['nullable', 'string', 'max:50', 'unique:investment_symbols,isin'];
    }

    protected function selectedType(): ?InvestmentSymbolType
    {
        return InvestmentSymbolType::tryFrom((string) $this->input('type'));
    }

    protected function selectedPriceSource(): ?InvestmentPriceSource
    {
        return InvestmentPriceSource::tryFrom(
            mb_strtolower(trim((string) $this->input('price_source')))
        );
    }

    protected function normalizedExternalSourceId(?InvestmentPriceSource $priceSource = null): ?string
    {
        $priceSource ??= $this->selectedPriceSource();

        if (! $this->filled('external_source_id') || $priceSource === InvestmentPriceSource::MANUAL) {
            return null;
        }

        $externalSourceId = trim((string) $this->input('external_source_id'));

        return match ($priceSource) {
            InvestmentPriceSource::YFAPI, InvestmentPriceSource::LJSE => mb_strtoupper($externalSourceId),
            default => $externalSourceId,
        };
    }
}
