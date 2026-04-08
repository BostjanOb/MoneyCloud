<?php

namespace App\Http\Requests;

use App\Enums\Employee;
use App\Models\SavingsAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'owner' => ['required', Rule::enum(Employee::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'apy' => ['required', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('savings_accounts', 'id')->whereNull('parent_id'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->filled('parent_id') ? $this->input('parent_id') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentAccount = $this->parentAccount();

            if ($parentAccount === null) {
                return;
            }

            $currentAccount = $this->currentAccount();

            if ($currentAccount !== null && $parentAccount->is($currentAccount)) {
                $validator->errors()->add('parent_id', 'Račun ne more biti sam sebi nadrejeni.');
            }
        });
    }

    protected function currentAccount(): ?SavingsAccount
    {
        $account = $this->route('savingsAccount');

        return $account instanceof SavingsAccount ? $account : null;
    }

    protected function parentAccount(): ?SavingsAccount
    {
        if (! $this->filled('parent_id')) {
            return null;
        }

        return SavingsAccount::query()->find($this->input('parent_id'));
    }
}
