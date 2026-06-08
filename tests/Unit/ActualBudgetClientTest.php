<?php

use App\Services\ActualBudgetClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.actual_budget.api_key' => 'test-key',
        'services.actual_budget.base_url' => 'https://money-api.test/v1',
        'services.actual_budget.budget_sync_id' => 'budget-sync-id',
        'services.actual_budget.encryption_password' => 'secret',
        'services.actual_budget.transaction_page_size' => 2,
    ]);
});

test('it sends configured headers and query parameters', function () {
    Http::fake([
        'https://money-api.test/v1/budgets/budget-sync-id/accounts/account-1/transactions*' => Http::sequence()
            ->push(['data' => [
                ['id' => 'transaction-1', 'account' => 'account-1', 'date' => '2026-03-10', 'amount' => -100],
                ['id' => 'transaction-2', 'account' => 'account-1', 'date' => '2026-03-11', 'amount' => -200],
            ]])
            ->push(['data' => [
                ['id' => 'transaction-3', 'account' => 'account-1', 'date' => '2026-03-12', 'amount' => -300],
            ]]),
    ]);

    $transactions = app(ActualBudgetClient::class)->transactions(
        'account-1',
        CarbonImmutable::parse('2026-03-01'),
        CarbonImmutable::parse('2026-06-08'),
    );

    expect($transactions)->toHaveCount(3);

    Http::assertSent(function (Request $request): bool {
        return str_starts_with(
            $request->url(),
            'https://money-api.test/v1/budgets/budget-sync-id/accounts/account-1/transactions?',
        )
            && $request->hasHeader('x-api-key', 'test-key')
            && $request->hasHeader('budget-encryption-password', 'secret')
            && str_contains($request->url(), 'since_date=2026-03-01')
            && str_contains($request->url(), 'until_date=2026-06-08')
            && str_contains($request->url(), 'page=1')
            && str_contains($request->url(), 'limit=2');
    });
});

test('it treats actual empty pagination response as no transactions', function () {
    Http::fake([
        'https://money-api.test/v1/budgets/budget-sync-id/accounts/account-empty/transactions*' => Http::response([
            'error' => 'Page query parameter must be between 1 and 0. Changing limit parameter can also change the number of pages.',
        ], 400),
    ]);

    $transactions = app(ActualBudgetClient::class)->transactions(
        'account-empty',
        CarbonImmutable::parse('2026-03-01'),
        CarbonImmutable::parse('2026-06-08'),
    );

    expect($transactions)->toBe([]);
});
