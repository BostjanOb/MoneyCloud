<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends StorePersonRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $person = $this->route('person');

        if (! $person instanceof Person) {
            return parent::rules();
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('people', 'slug')->ignore($person->id),
            ],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
