<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaycheckYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'child1_months' => ['required', 'integer', 'between:0,12'],
            'child2_months' => ['required', 'integer', 'between:0,12'],
            'child3_months' => ['required', 'integer', 'between:0,12'],
        ];
    }
}
