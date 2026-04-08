<?php

namespace App\Http\Requests;

use App\Enums\BonusType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'paycheck_year_id' => ['required', 'exists:paycheck_years,id'],
            'type' => ['required', Rule::enum(BonusType::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'taxable' => ['boolean'],
            'paid_tax' => ['required_if:taxable,true', 'nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $taxable = $this->boolean('taxable');

        $this->merge([
            'taxable' => $taxable,
            'paid_tax' => $taxable ? $this->input('paid_tax') : 0,
        ]);
    }
}
