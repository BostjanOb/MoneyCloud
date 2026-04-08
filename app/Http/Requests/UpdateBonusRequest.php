<?php

namespace App\Http\Requests;

class UpdateBonusRequest extends StoreBonusRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['paycheck_year_id']);

        return $rules;
    }
}
