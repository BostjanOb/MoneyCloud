<?php

namespace App\Http\Requests;

use App\Enums\Employee;
use App\Models\SavingsAccount;
use Illuminate\Validation\Rule;

class UpdateSavingsAccountRequest extends StoreSavingsAccountRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $savingsAccount = $this->route('savingsAccount');

        if ($savingsAccount instanceof SavingsAccount && $savingsAccount->hasChildren()) {
            return [
                'name' => ['required', 'string', 'max:255'],
                'owner' => ['required', Rule::enum(Employee::class)],
                'apy' => ['required', 'numeric', 'min:0', 'max:100'],
                'sort_order' => ['required', 'integer', 'min:0'],
                'amount' => ['prohibited'],
                'parent_id' => ['prohibited'],
            ];
        }

        return parent::rules();
    }

    protected function prepareForValidation(): void
    {
        if ($this->currentAccount()?->hasChildren()) {
            return;
        }

        parent::prepareForValidation();
    }
}
