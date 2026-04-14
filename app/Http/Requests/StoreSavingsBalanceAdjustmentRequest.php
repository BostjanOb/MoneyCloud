<?php

namespace App\Http\Requests;

use App\Models\SavingsAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavingsBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', Rule::in(['add', 'subtract'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'related_account_id' => ['nullable', 'integer', Rule::exists('savings_accounts', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'related_account_id' => $this->filled('related_account_id')
                ? $this->input('related_account_id')
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $targetAccount = $this->targetAccount();

            if ($targetAccount !== null && $targetAccount->hasChildren()) {
                $validator->errors()->add(
                    'amount',
                    'Stanje lahko spreminjate le na leaf računu brez podračunov.',
                );
            }

            $relatedAccount = $this->relatedAccount();

            if ($relatedAccount === null) {
                return;
            }

            if ($targetAccount !== null && $relatedAccount->is($targetAccount)) {
                $validator->errors()->add(
                    'related_account_id',
                    'Drugi račun mora biti drugačen od izbranega računa.',
                );
            }

            if ($relatedAccount->hasChildren()) {
                $validator->errors()->add(
                    'related_account_id',
                    'Izbrani drugi račun mora biti leaf račun brez podračunov.',
                );
            }
        });
    }

    public function operation(): string
    {
        return (string) $this->validated('operation');
    }

    public function amount(): string
    {
        return (string) $this->validated('amount');
    }

    public function relatedAccountId(): ?int
    {
        if (! $this->filled('related_account_id')) {
            return null;
        }

        return (int) $this->validated('related_account_id');
    }

    private function targetAccount(): ?SavingsAccount
    {
        $account = $this->route('savingsAccount');

        return $account instanceof SavingsAccount ? $account : null;
    }

    private function relatedAccount(): ?SavingsAccount
    {
        if (! $this->filled('related_account_id')) {
            return null;
        }

        return SavingsAccount::query()->find($this->input('related_account_id'));
    }
}
