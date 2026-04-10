<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaycheckYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'person_id' => [
                'required',
                'integer',
                Rule::exists('people', 'id')->where('is_active', true),
            ],
            'year' => ['required', 'integer', 'min:2020'],
            'child1_months' => ['required', 'integer', 'between:0,12'],
            'child2_months' => ['required', 'integer', 'between:0,12'],
            'child3_months' => ['required', 'integer', 'between:0,12'],
        ];
    }
}
