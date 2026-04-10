<?php

namespace App\Http\Requests;

use App\Enums\InvestmentSymbolType;
use App\Models\SavingsAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvestmentProviderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('investment_providers', 'slug')],
            'sort_order' => ['required', 'integer', 'min:0'],
            'requires_linked_savings_account' => ['required', 'boolean'],
            'linked_savings_account_id' => ['nullable', 'integer', Rule::exists('savings_accounts', 'id')],
            'supported_symbol_types' => ['required', 'array', 'min:1'],
            'supported_symbol_types.*' => ['required', 'distinct', Rule::enum(InvestmentSymbolType::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $requiresLinkedSavingsAccount = $this->boolean('requires_linked_savings_account');

        $this->merge([
            'slug' => Str::slug((string) ($this->filled('slug') ? $this->input('slug') : $this->input('name'))),
            'sort_order' => $this->filled('sort_order')
                ? (int) $this->input('sort_order')
                : $this->input('sort_order'),
            'requires_linked_savings_account' => $requiresLinkedSavingsAccount,
            'linked_savings_account_id' => $requiresLinkedSavingsAccount && $this->filled('linked_savings_account_id')
                ? (int) $this->input('linked_savings_account_id')
                : null,
            'supported_symbol_types' => collect($this->input('supported_symbol_types', []))
                ->filter(fn (mixed $type): bool => is_string($type) && $type !== '')
                ->map(fn (string $type): string => mb_strtolower($type))
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $account = $this->linkedSavingsAccount();

                if ($account !== null && $account->children()->exists()) {
                    $validator->errors()->add(
                        'linked_savings_account_id',
                        'Izbrani račun mora biti leaf račun brez podračunov.',
                    );
                }
            },
        ];
    }

    protected function linkedSavingsAccount(): ?SavingsAccount
    {
        if (! $this->filled('linked_savings_account_id')) {
            return null;
        }

        return SavingsAccount::query()->find($this->integer('linked_savings_account_id'));
    }
}
