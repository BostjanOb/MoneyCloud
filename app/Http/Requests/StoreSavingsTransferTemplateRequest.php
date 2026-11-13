<?php

namespace App\Http\Requests;

use App\Models\SavingsAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavingsTransferTemplateRequest extends FormRequest
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
            'source_account_id' => ['nullable', 'integer', Rule::exists('savings_accounts', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.savings_account_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('savings_accounts', 'id'),
            ],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Dodajte vsaj en ciljni račun.',
            'items.*.savings_account_id.required' => 'Izberite ciljni račun.',
            'items.*.savings_account_id.distinct' => 'Ciljni račun je izbran večkrat.',
            'items.*.amount.required' => 'Vnesite znesek.',
            'items.*.amount.gt' => 'Znesek mora biti večji od 0.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $accountIds = collect($this->input('items', []))
                ->pluck('savings_account_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id);

            $accountIdsWithChildren = SavingsAccount::whereIn('id', $accountIds)
                ->has('children')
                ->pluck('id');

            foreach ($accountIds as $index => $accountId) {
                if ($accountIdsWithChildren->contains($accountId)) {
                    $validator->errors()->add(
                        "items.{$index}.savings_account_id",
                        'Ciljni račun mora biti leaf račun brez podračunov.',
                    );
                }
            }

            if (! $this->filled('source_account_id') || $validator->errors()->has('source_account_id')) {
                return;
            }

            $sourceAccountId = (int) $this->input('source_account_id');

            if ($accountIds->contains($sourceAccountId)) {
                $validator->errors()->add('source_account_id', 'Izvorni račun ne sme biti med ciljnimi računi.');
            }

            if (SavingsAccount::whereKey($sourceAccountId)->has('children')->exists()) {
                $validator->errors()->add('source_account_id', 'Izvorni račun mora biti leaf račun brez podračunov.');
            }
        });
    }

    /** @return array<int, int> */
    public function amountsInCentsByAccountId(): array
    {
        return collect($this->validated('items'))
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['savings_account_id'] => SavingsAccount::toCents($item['amount']),
            ])
            ->all();
    }
}
