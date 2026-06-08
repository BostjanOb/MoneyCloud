<?php

use App\Ai\Tools\GetActualTransactions;
use App\Services\ActualBudgetContextService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-08 12:00:00', 'Europe/Ljubljana'));

    Cache::forget(ActualBudgetContextService::CACHE_KEY);

    config([
        'services.actual_budget.api_key' => 'test-key',
        'services.actual_budget.base_url' => 'https://money-api.test/v1',
        'services.actual_budget.budget_sync_id' => 'budget-sync-id',
        'services.actual_budget.encryption_password' => null,
        'services.actual_budget.transaction_page_size' => 50,
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('it refreshes and enriches actual budget context', function () {
    fakeActualBudgetApi();

    $context = app(ActualBudgetContextService::class)->refreshChatContext();
    $transactions = collect($context['transactions']);
    $hiddenCategoryTransaction = $transactions->firstWhere('id', 'transaction-hidden');

    expect($context['window'])->toMatchArray([
        'days' => 90,
        'since' => '2026-03-10',
        'until' => '2026-06-08',
    ])
        ->and($context['accounts'])->toHaveCount(3)
        ->and(collect($context['accounts'])->pluck('id'))->toContain('account-offbudget', 'account-closed')
        ->and(collect($context['accounts'])->pluck('id'))->not->toContain('account-closed-empty')
        ->and($hiddenCategoryTransaction['amount_raw'])->toBe(-123456)
        ->and($hiddenCategoryTransaction['amount_eur'])->toBe(-1234.56)
        ->and($hiddenCategoryTransaction['amount_formatted'])->toBe('-1.234,56 €')
        ->and($hiddenCategoryTransaction['category_name'])->toBe('Skrita poraba')
        ->and($hiddenCategoryTransaction['category_group_name'])->toBe('Skrite kategorije')
        ->and($hiddenCategoryTransaction['category_hidden'])->toBeTrue()
        ->and($hiddenCategoryTransaction['payee_name'])->toBe('Mercator')
        ->and($hiddenCategoryTransaction['account_offbudget'])->toBeFalse()
        ->and($transactions->firstWhere('id', 'transaction-offbudget')['account_offbudget'])->toBeTrue();
});

test('it summarizes spending by category and excludes transfers', function () {
    fakeActualBudgetApi();

    app(ActualBudgetContextService::class)->refreshChatContext();

    $summary = app(ActualBudgetContextService::class)->spendingByCategory();
    $hiddenCategory = collect($summary['categories'])->firstWhere('category_id', 'category-hidden');

    expect($hiddenCategory['spent_eur'])->toBe(1234.56)
        ->and($hiddenCategory['transaction_count'])->toBe(1)
        ->and($hiddenCategory['top_payees'][0])->toMatchArray([
            'payee' => 'Mercator',
            'spent_eur' => 1234.56,
            'transaction_count' => 1,
        ]);
});

test('chat transaction tool uses cache without calling actual api', function () {
    Cache::forever(ActualBudgetContextService::CACHE_KEY, [
        'available' => true,
        'source' => 'cache',
        'generated_at' => '2026-06-08T12:00:00+02:00',
        'window' => ['days' => 90, 'since' => '2026-03-10', 'until' => '2026-06-08'],
        'warnings' => [],
        'transactions' => [
            ['id' => 'cached-transaction', 'date' => '2026-06-01', 'account_id' => 'account-1', 'category_id' => 'category-1'],
        ],
    ]);
    Http::fake();

    $result = json_decode((string) app(GetActualTransactions::class)->handle(new Request), true);

    expect($result['transactions'][0]['id'])->toBe('cached-transaction');
    Http::assertNothingSent();
});

test('report context falls back to stale chat cache when actual api is unavailable', function () {
    Cache::forever(ActualBudgetContextService::CACHE_KEY, [
        'available' => true,
        'source' => 'cache',
        'generated_at' => '2026-06-08T12:00:00+02:00',
        'window' => ['days' => 90, 'since' => '2026-03-10', 'until' => '2026-06-08'],
        'warnings' => [],
        'accounts' => [],
        'category_groups' => [],
        'categories' => [],
        'payees' => [],
        'budget_months' => [],
        'transactions' => [],
    ]);
    Http::fake([
        'https://money-api.test/*' => Http::response(['error' => 'Actual ni dosegljiv.'], 500),
    ]);

    $context = app(ActualBudgetContextService::class)->reportContext();

    expect($context['source'])->toBe('cache')
        ->and($context['warnings'])->toContain(ActualBudgetContextService::STALE_WARNING);
});

function fakeActualBudgetApi(): void
{
    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        if (str_ends_with($path, '/accounts')) {
            return Http::response(['data' => [
                ['id' => 'account-checking', 'name' => 'TRR', 'offbudget' => false, 'closed' => false],
                ['id' => 'account-offbudget', 'name' => 'Gotovina', 'offbudget' => true, 'closed' => false],
                ['id' => 'account-closed', 'name' => 'Zaprt račun', 'offbudget' => false, 'closed' => true],
                ['id' => 'account-closed-empty', 'name' => 'Prazen zaprt račun', 'offbudget' => false, 'closed' => true],
            ]]);
        }

        if (str_ends_with($path, '/categorygroups')) {
            return Http::response(['data' => [
                ['id' => 'group-hidden', 'name' => 'Skrite kategorije', 'is_income' => false, 'hidden' => true],
                ['id' => 'group-food', 'name' => 'Hrana', 'is_income' => false, 'hidden' => false],
            ]]);
        }

        if (str_ends_with($path, '/categories')) {
            return Http::response(['data' => [
                ['id' => 'category-hidden', 'name' => 'Skrita poraba', 'group_id' => 'group-hidden', 'is_income' => false, 'hidden' => true],
                ['id' => 'category-food', 'name' => 'Živila', 'group_id' => 'group-food', 'is_income' => false, 'hidden' => false],
            ]]);
        }

        if (str_ends_with($path, '/payees')) {
            return Http::response(['data' => [
                ['id' => 'payee-mercator', 'name' => 'Mercator', 'category' => 'category-food'],
                ['id' => 'payee-income', 'name' => 'Plača'],
            ]]);
        }

        if (str_contains($path, '/months/')) {
            $month = str($path)->after('/months/')->toString();

            return Http::response(['data' => [
                'month' => $month,
                'incomeAvailable' => 100000,
                'totalBudgeted' => 90000,
                'totalIncome' => 250000,
                'totalSpent' => -75000,
                'totalBalance' => 15000,
                'categoryGroups' => [[
                    'id' => 'group-hidden',
                    'name' => 'Skrite kategorije',
                    'is_income' => false,
                    'hidden' => true,
                    'budgeted' => 10000,
                    'spent' => -123456,
                    'balance' => -113456,
                    'categories' => [[
                        'id' => 'category-hidden',
                        'name' => 'Skrita poraba',
                        'group_id' => 'group-hidden',
                        'is_income' => false,
                        'hidden' => true,
                        'budgeted' => 10000,
                        'spent' => -123456,
                        'balance' => -113456,
                        'carryover' => false,
                    ]],
                ]],
            ]]);
        }

        if (str_contains($path, '/accounts/account-checking/transactions')) {
            return Http::response(['data' => [
                [
                    'id' => 'transaction-hidden',
                    'account' => 'account-checking',
                    'date' => '2026-06-01',
                    'amount' => -123456,
                    'payee' => 'payee-mercator',
                    'imported_payee' => 'MERCATOR',
                    'category' => 'category-hidden',
                    'notes' => 'Test',
                    'imported_id' => 'imported-1',
                    'transfer_id' => null,
                    'cleared' => true,
                    'subtransactions' => [],
                ],
                [
                    'id' => 'transaction-transfer',
                    'account' => 'account-checking',
                    'date' => '2026-06-02',
                    'amount' => -5000,
                    'payee' => 'payee-mercator',
                    'category' => 'category-food',
                    'transfer_id' => 'transfer-1',
                    'subtransactions' => [],
                ],
            ]]);
        }

        if (str_contains($path, '/accounts/account-offbudget/transactions')) {
            return Http::response(['data' => [
                [
                    'id' => 'transaction-offbudget',
                    'account' => 'account-offbudget',
                    'date' => '2026-06-03',
                    'amount' => 120000,
                    'payee' => 'payee-income',
                    'category' => null,
                    'transfer_id' => null,
                    'subtransactions' => [],
                ],
            ]]);
        }

        if (str_contains($path, '/accounts/account-closed/transactions')) {
            return Http::response(['data' => [
                [
                    'id' => 'transaction-closed',
                    'account' => 'account-closed',
                    'date' => '2026-05-01',
                    'amount' => -100,
                    'payee' => 'payee-mercator',
                    'category' => 'category-food',
                    'transfer_id' => null,
                    'subtransactions' => [],
                ],
            ]]);
        }

        if (str_contains($path, '/accounts/account-closed-empty/transactions')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['data' => []]);
    });
}
