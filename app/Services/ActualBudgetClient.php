<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ActualBudgetClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.actual_budget.api_key'))
            && filled(config('services.actual_budget.budget_sync_id'));
    }

    public function accounts(): array
    {
        return $this->data(
            $this->request()->get($this->budgetPath('accounts')),
        );
    }

    public function categories(): array
    {
        return $this->data(
            $this->request()->get($this->budgetPath('categories')),
        );
    }

    public function categoryGroups(): array
    {
        return $this->data(
            $this->request()->get($this->budgetPath('categorygroups')),
        );
    }

    public function payees(): array
    {
        return $this->data(
            $this->request()->get($this->budgetPath('payees')),
        );
    }

    public function budgetMonth(string $month): array
    {
        return $this->data(
            $this->request()->get($this->budgetPath("months/{$month}")),
        );
    }

    public function transactions(string $accountId, CarbonInterface $since, CarbonInterface $until): array
    {
        $page = 1;
        $transactions = [];
        $limit = max(1, (int) config('services.actual_budget.transaction_page_size', 200));

        do {
            $data = $this->transactionPage($accountId, $since, $until, $page, $limit);

            $transactions = array_merge($transactions, $data);
            $page++;
        } while (count($data) === $limit);

        return $transactions;
    }

    private function transactionPage(
        string $accountId,
        CarbonInterface $since,
        CarbonInterface $until,
        int $page,
        int $limit,
    ): array {
        try {
            return $this->data($this->request()->get(
                $this->budgetPath("accounts/{$accountId}/transactions"),
                [
                    'since_date' => $since->toDateString(),
                    'until_date' => $until->toDateString(),
                    'page' => $page,
                    'limit' => $limit,
                ],
            ));
        } catch (RequestException $exception) {
            $error = $exception->response->json('error');

            if (is_string($error) && str_contains($error, 'Page query parameter must be between')) {
                return [];
            }

            throw $exception;
        }
    }

    private function request(): PendingRequest
    {
        $apiKey = config('services.actual_budget.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Actual Budget API ključ ni nastavljen.');
        }

        $headers = ['x-api-key' => $apiKey];
        $encryptionPassword = config('services.actual_budget.encryption_password');

        if (is_string($encryptionPassword) && $encryptionPassword !== '') {
            $headers['budget-encryption-password'] = $encryptionPassword;
        }

        return Http::acceptJson()
            ->withHeaders($headers)
            ->timeout(30)
            ->connectTimeout(10)
            ->retry([200, 500, 1000], throw: false);
    }

    private function budgetPath(string $path): string
    {
        $budgetSyncId = config('services.actual_budget.budget_sync_id');

        if (! is_string($budgetSyncId) || $budgetSyncId === '') {
            throw new RuntimeException('Actual Budget sync ID ni nastavljen.');
        }

        return sprintf(
            '%s/budgets/%s/%s',
            rtrim((string) config('services.actual_budget.base_url'), '/'),
            rawurlencode($budgetSyncId),
            ltrim($path, '/'),
        );
    }

    private function data(mixed $response): array
    {
        /** @var Response $response */
        $payload = $response->throw()->json();

        if (! is_array($payload)) {
            return [];
        }

        $data = $payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }
}
