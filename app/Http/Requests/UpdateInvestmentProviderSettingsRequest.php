<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use App\Models\InvestmentProvider;
use Illuminate\Validation\Rule;

class UpdateInvestmentProviderSettingsRequest extends StoreInvestmentProviderSettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $provider = $this->route('investmentProvider');

        if (! $provider instanceof InvestmentProvider) {
            return parent::rules();
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('investment_providers', 'slug')->ignore($provider->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
            'requires_linked_savings_account' => ['required', 'boolean'],
            'linked_savings_account_id' => ['nullable', 'integer', Rule::exists('savings_accounts', 'id')],
            'supported_symbol_types' => ['required', 'array', 'min:1'],
            'supported_symbol_types.*' => ['required', 'distinct', Rule::enum(InvestmentSymbolType::class)],
        ];
    }
}
