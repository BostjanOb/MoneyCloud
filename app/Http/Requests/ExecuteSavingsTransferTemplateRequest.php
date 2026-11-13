<?php

namespace App\Http\Requests;

use App\Models\SavingsAccount;
use App\Models\SavingsTransferTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExecuteSavingsTransferTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_account_id' => [
                Rule::requiredIf($this->template()->source_account_id === null),
                'nullable',
                'integer',
                Rule::exists('savings_accounts', 'id'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'source_account_id.required' => 'Izberite izvorni račun.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('source_account_id')) {
                return;
            }

            if ($this->template()->items->isEmpty()) {
                $validator->errors()->add('source_account_id', 'Predloga nima ciljnih računov.');

                return;
            }

            $sourceAccount = $this->sourceAccount();

            if ($sourceAccount->hasChildren()) {
                $validator->errors()->add(
                    'source_account_id',
                    'Izvorni račun mora biti leaf račun brez podračunov.',
                );
            }

            if ($this->template()->items->contains('savings_account_id', $sourceAccount->id)) {
                $validator->errors()->add(
                    'source_account_id',
                    'Izvorni račun ne sme biti med ciljnimi računi.',
                );
            }
        });
    }

    /**
     * The account chosen in the request, falling back to the template's saved source.
     */
    public function sourceAccount(): SavingsAccount
    {
        return SavingsAccount::findOrFail(
            $this->input('source_account_id') ?? $this->template()->source_account_id,
        );
    }

    private function template(): SavingsTransferTemplate
    {
        /** @var SavingsTransferTemplate $template */
        $template = $this->route('savingsTransferTemplate');

        return $template;
    }
}
