<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaycheckRequest extends FormRequest
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
            'month' => ['required', 'integer', 'between:1,12'],
            'net' => ['nullable', 'numeric', 'min:0'],
            'gross' => ['required', 'numeric', 'min:0'],
            'contributions' => ['required', 'numeric', 'min:0'],
            'taxes' => ['required', 'numeric', 'min:0'],
        ];
    }
}
