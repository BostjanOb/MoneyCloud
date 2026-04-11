<?php

namespace App\Http\Requests;

use App\Models\MonthlyPortfolioSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class UpdateMonthlyPortfolioSnapshotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var MonthlyPortfolioSnapshot|null $monthlySnapshot */
        $monthlySnapshot = $this->route('monthlySnapshot');

        return [
            'month_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($monthlySnapshot): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $exists = MonthlyPortfolioSnapshot::query()
                        ->whereDate('month_date', $value)
                        ->when(
                            $monthlySnapshot instanceof MonthlyPortfolioSnapshot,
                            fn ($query) => $query->whereKeyNot($monthlySnapshot->getKey()),
                        )
                        ->exists();

                    if ($exists) {
                        $fail('Izbrani mesec že obstaja.');
                    }
                },
            ],
            'savings_amount' => ['required', 'numeric', 'min:0'],
            'bond_amount' => ['required', 'numeric', 'min:0'],
            'etf_amount' => ['required', 'numeric', 'min:0'],
            'crypto_amount' => ['required', 'numeric', 'min:0'],
            'stock_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $monthDate = $this->normalizeMonthDate($this->input('month_date'));

        if ($monthDate !== null) {
            $this->merge([
                'month_date' => $monthDate,
            ]);
        }
    }

    private function normalizeMonthDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return $value.'-01';
        }

        try {
            return CarbonImmutable::parse($value)->startOfMonth()->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
