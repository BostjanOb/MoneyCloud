<?php

namespace App\Http\Requests;

use App\Models\InvestmentProvider;
use App\Models\SavingsAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvestmentProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'linked_savings_account_id' => [
                'nullable',
                'integer',
                Rule::exists('savings_accounts', 'id'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'linked_savings_account_id' => $this->filled('linked_savings_account_id')
                ? (int) $this->input('linked_savings_account_id')
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $provider = $this->route('investmentProvider');
            $account = $this->linkedSavingsAccount();

            if (! $provider instanceof InvestmentProvider) {
                return;
            }

            if (
                ! $provider->requiresLinkedSavingsAccount()
                && $this->filled('linked_savings_account_id')
            ) {
                $validator->errors()->add(
                    'linked_savings_account_id',
                    sprintf('Povezava z varčevalnim računom ni omogočena za ponudnika %s.', $provider->name),
                );
            }

            if ($account !== null && $account->children()->exists()) {
                $validator->errors()->add(
                    'linked_savings_account_id',
                    'Izbrani račun mora biti leaf račun brez podračunov.',
                );
            }
        });
    }

    private function linkedSavingsAccount(): ?SavingsAccount
    {
        if (! $this->filled('linked_savings_account_id')) {
            return null;
        }

        return SavingsAccount::query()->find($this->integer('linked_savings_account_id'));
    }
}
