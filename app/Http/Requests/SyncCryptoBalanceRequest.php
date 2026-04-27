<?php

namespace App\Http\Requests;

use App\Models\InvestmentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncCryptoBalanceRequest extends FormRequest
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'investment_provider_id' => $this->filled('investment_provider_id')
                ? $this->integer('investment_provider_id')
                : null,
        ]);
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $provider = $this->selectedProvider();

                if ($provider !== null && ! $provider->canSyncBalances()) {
                    $validator->errors()->add(
                        'investment_provider_id',
                        'Izbrana platforma ne podpira sinhronizacije stanj.',
                    );
                }
            },
        ];
    }

    public function selectedProvider(): ?InvestmentProvider
    {
        if (! $this->filled('investment_provider_id')) {
            return null;
        }

        return InvestmentProvider::find($this->integer('investment_provider_id'));
    }
}
