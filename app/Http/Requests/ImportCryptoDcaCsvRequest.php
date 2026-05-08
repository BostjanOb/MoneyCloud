<?php

namespace App\Http\Requests;

use App\Models\InvestmentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportCryptoDcaCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'investment_provider_id' => ['required', 'integer', Rule::exists('investment_providers', 'id')],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'add_to_balance' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'add_to_balance' => $this->boolean('add_to_balance'),
            'investment_provider_id' => $this->filled('investment_provider_id')
                ? $this->integer('investment_provider_id')
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('investment_provider_id')) {
                return;
            }

            $provider = InvestmentProvider::query()->find($this->integer('investment_provider_id'));

            if ($provider !== null && ! $provider->supportsCrypto()) {
                $validator->errors()->add(
                    'investment_provider_id',
                    'Izbrana platforma ne podpira kripta.',
                );
            }
        });
    }

    public function csvFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    public function providerId(): int
    {
        return (int) $this->validated()['investment_provider_id'];
    }

    public function shouldAddToBalance(): bool
    {
        return (bool) ($this->validated()['add_to_balance'] ?? false);
    }
}
