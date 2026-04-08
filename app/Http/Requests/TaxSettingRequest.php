<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class TaxSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year_from' => ['required', 'integer', 'min:2019'],
            'year_to' => ['nullable', 'integer', 'min:2019', 'gte:year_from'],
            'general_relief_brackets' => ['required', 'array', 'min:1'],
            'general_relief_brackets.*.income_from' => ['required', 'numeric', 'min:0'],
            'general_relief_brackets.*.income_to' => ['nullable', 'numeric', 'min:0'],
            'general_relief_brackets.*.base_relief' => ['required', 'numeric', 'min:0'],
            'general_relief_brackets.*.formula_constant' => ['nullable', 'numeric', 'min:0'],
            'general_relief_brackets.*.formula_multiplier' => ['nullable', 'numeric', 'min:0'],
            'child_relief1' => ['required', 'numeric', 'min:0'],
            'child_relief2' => ['required', 'numeric', 'min:0'],
            'child_relief3' => ['required', 'numeric', 'min:0'],
            'brackets' => ['required', 'array', 'min:1'],
            'brackets.*.bracket_from' => ['required', 'numeric', 'min:0'],
            'brackets.*.bracket_to' => ['nullable', 'numeric', 'min:0'],
            'brackets.*.base_tax' => ['required', 'numeric', 'min:0'],
            'brackets.*.rate' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateGeneralReliefBrackets($validator),
        ];
    }

    private function validateGeneralReliefBrackets(Validator $validator): void
    {
        $generalReliefBrackets = collect($this->input('general_relief_brackets', []))
            ->sortBy('income_from', SORT_NUMERIC)
            ->values();

        $generalReliefBrackets->each(function (array $bracket, int $index) use ($generalReliefBrackets, $validator): void {
            $incomeFrom = (float) ($bracket['income_from'] ?? 0);
            $incomeTo = $bracket['income_to'];
            $formulaConstant = $bracket['formula_constant'] ?? null;
            $formulaMultiplier = $bracket['formula_multiplier'] ?? null;
            $hasFormulaConstant = $formulaConstant !== null && $formulaConstant !== '';
            $hasFormulaMultiplier = $formulaMultiplier !== null && $formulaMultiplier !== '';

            if ($hasFormulaConstant xor $hasFormulaMultiplier) {
                $validator->errors()->add(
                    "general_relief_brackets.{$index}",
                    'Konstanta in koeficient formule morata biti izpolnjena skupaj.'
                );
            }

            if ($incomeTo !== null && $incomeTo !== '' && (float) $incomeTo <= $incomeFrom) {
                $validator->errors()->add(
                    "general_relief_brackets.{$index}.income_to",
                    'Zgornja meja mora biti večja od spodnje meje.'
                );
            }

            if ($index < $generalReliefBrackets->count() - 1 && ($incomeTo === null || $incomeTo === '')) {
                $validator->errors()->add(
                    "general_relief_brackets.{$index}.income_to",
                    'Samo zadnji razred sme imeti prazno zgornjo mejo.'
                );
            }

            if ($index === 0) {
                return;
            }

            $previousIncomeTo = $generalReliefBrackets[$index - 1]['income_to'] ?? null;

            if ($previousIncomeTo === null || $previousIncomeTo === '') {
                $validator->errors()->add(
                    "general_relief_brackets.{$index}.income_from",
                    'Po odprtem razredu ne more slediti nov razred.'
                );

                return;
            }

            if ($incomeFrom < (float) $previousIncomeTo) {
                $validator->errors()->add(
                    "general_relief_brackets.{$index}.income_from",
                    'Razredi splošne olajšave se ne smejo prekrivati.'
                );
            }
        });
    }
}
