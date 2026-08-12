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
        'days' => 365,
        'since' => '2025-06-08',
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

test('it expands split transactions into their parts', function () {
    fakeActualBudgetApi();

    $service = app(ActualBudgetContextService::class);
    $service->refreshChatContext();

    $rows = collect($service->transactions()['transactions']);
    $part = $rows->firstWhere('id', 'transaction-split-food');

    expect($rows->pluck('id'))->not->toContain('transaction-split')
        ->and($rows->pluck('id'))->toContain('transaction-split-food', 'transaction-split-rest')
        ->and($part)->toMatchArray([
            'category_id' => 'category-food',
            'category_name' => 'Živila',
            'category_group_name' => 'Hrana',
            'payee_name' => 'Mercator',
            'imported_payee' => 'MERCATOR SPLIT',
            'notes' => 'Deljen nakup',
            'account_name' => 'TRR',
            'date' => '2026-06-04',
            'amount_eur' => -60.0,
            'amount_formatted' => '-60,00 €',
            'is_split_child' => true,
            'split_parent_id' => 'transaction-split',
        ]);
});

test('split parts land in their own categories instead of uncategorized', function () {
    fakeActualBudgetApi();

    $service = app(ActualBudgetContextService::class);
    $service->refreshChatContext();

    $categories = collect($service->spendingByCategory()['categories']);
    $food = $categories->firstWhere('category_id', 'category-food');
    $uncategorized = $categories->firstWhere('category_name', 'Brez kategorije');

    expect($food['spent_eur'])->toBe(91.0)
        ->and($food['transaction_count'])->toBe(3)
        ->and($uncategorized['spent_eur'])->toBe(60.0)
        ->and($uncategorized['income_eur'])->toBe(1200.0)
        ->and($uncategorized['transaction_count'])->toBe(3);
});

test('an unbalanced split keeps its difference as an uncategorized remainder', function () {
    fakeActualBudgetApi();

    $service = app(ActualBudgetContextService::class);
    $context = $service->refreshChatContext();

    $rows = collect($service->transactions()['transactions']);
    $remainder = $rows->firstWhere('id', 'transaction-closed-split:remainder');

    expect($remainder['amount_raw'])->toBe(-2000)
        ->and($remainder['category_id'])->toBeNull()
        ->and($remainder['category_name'])->toBe('Brez kategorije')
        ->and($remainder['is_split_remainder'])->toBeTrue()
        ->and($rows->sum('amount_raw'))->toBe(collect($context['transactions'])->sum('amount_raw'));
});

test('filtering by category finds split parts', function () {
    fakeActualBudgetApi();

    $service = app(ActualBudgetContextService::class);
    $service->refreshChatContext();

    $filtered = $service->transactions(['category_id' => 'category-food']);

    expect(collect($filtered['transactions'])->pluck('id'))
        ->toContain('transaction-split-food', 'transaction-closed-split-food')
        ->and($filtered['total_matching'])->toBe(4);
});

test('splits in an already cached context are expanded without refreshing', function () {
    Cache::forever(ActualBudgetContextService::CACHE_KEY, cachedSplitContext());
    Http::fake();

    $result = json_decode((string) app(GetActualTransactions::class)->handle(new Request), true);
    $rows = collect($result['transactions']);

    expect($rows->pluck('id'))->toContain('cached-child')
        ->and($rows->pluck('id'))->not->toContain('cached-parent')
        ->and($rows->firstWhere('id', 'cached-child')['payee_name'])->toBe('Mercator');

    Http::assertNothingSent();
});

test('a split part echoed at top level is not counted twice', function () {
    $context = cachedSplitContext();
    $context['transactions'][] = $context['transactions'][0]['subtransactions'][0];

    Cache::forever(ActualBudgetContextService::CACHE_KEY, $context);
    Http::fake();

    $rows = collect(app(ActualBudgetContextService::class)->transactions()['transactions']);

    expect($rows->where('id', 'cached-child'))->toHaveCount(1)
        ->and($rows)->toHaveCount(1);
});

test('a split part whose parent is outside the window is kept', function () {
    $context = cachedSplitContext();
    $orphan = $context['transactions'][0]['subtransactions'][0];
    $orphan['id'] = 'orphan-child';
    $orphan['raw']['parent_id'] = 'parent-outside-window';
    $context['transactions'][] = $orphan;

    Cache::forever(ActualBudgetContextService::CACHE_KEY, $context);
    Http::fake();

    $rows = collect(app(ActualBudgetContextService::class)->transactions()['transactions']);

    expect($rows->pluck('id'))->toContain('orphan-child');
});

test('chat transaction tool uses cache without calling actual api', function () {
    Cache::forever(ActualBudgetContextService::CACHE_KEY, [
        'available' => true,
        'source' => 'cache',
        'generated_at' => '2026-06-08T12:00:00+02:00',
        'window' => ['days' => 365, 'since' => '2025-06-08', 'until' => '2026-06-08'],
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
        'window' => ['days' => 365, 'since' => '2025-06-08', 'until' => '2026-06-08'],
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

/**
 * A context shaped like one written to the cache before split expansion existed:
 * a parent row carrying the total with the real category nested underneath.
 *
 * @return array<string, mixed>
 */
function cachedSplitContext(): array
{
    return [
        'available' => true,
        'source' => 'cache',
        'generated_at' => '2026-06-08T12:00:00+02:00',
        'window' => ['days' => 365, 'since' => '2025-06-08', 'until' => '2026-06-08'],
        'warnings' => [],
        'transactions' => [
            [
                'id' => 'cached-parent',
                'date' => '2026-06-01',
                'account_id' => 'account-1',
                'amount_raw' => -3000,
                'category_id' => null,
                'category_name' => 'Brez kategorije',
                'payee_name' => 'Mercator',
                'is_transfer' => false,
                'raw' => ['is_parent' => true],
                'subtransactions' => [
                    [
                        'id' => 'cached-child',
                        'date' => '2026-06-01',
                        'account_id' => 'account-1',
                        'amount_raw' => -3000,
                        'category_id' => 'category-1',
                        'category_name' => 'Živila',
                        'payee_name' => null,
                        'is_transfer' => false,
                        'raw' => ['is_child' => true, 'parent_id' => 'cached-parent'],
                        'subtransactions' => [],
                    ],
                ],
            ],
        ],
    ];
}

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
                [
                    'id' => 'transaction-split',
                    'account' => 'account-checking',
                    'date' => '2026-06-04',
                    'amount' => -10000,
                    'payee' => 'payee-mercator',
                    'imported_payee' => 'MERCATOR SPLIT',
                    'category' => null,
                    'notes' => 'Deljen nakup',
                    'transfer_id' => null,
                    'cleared' => true,
                    'is_parent' => true,
                    'subtransactions' => [
                        [
                            'id' => 'transaction-split-food',
                            'account' => 'account-checking',
                            'date' => '2026-06-04',
                            'amount' => -6000,
                            'payee' => null,
                            'category' => 'category-food',
                            'transfer_id' => null,
                            'is_child' => true,
                            'parent_id' => 'transaction-split',
                            'subtransactions' => [],
                        ],
                        [
                            'id' => 'transaction-split-rest',
                            'account' => 'account-checking',
                            'date' => '2026-06-04',
                            'amount' => -4000,
                            'payee' => null,
                            'category' => null,
                            'transfer_id' => null,
                            'is_child' => true,
                            'parent_id' => 'transaction-split',
                            'subtransactions' => [],
                        ],
                    ],
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
                [
                    'id' => 'transaction-closed-split',
                    'account' => 'account-closed',
                    'date' => '2026-05-02',
                    'amount' => -5000,
                    'payee' => 'payee-mercator',
                    'category' => null,
                    'transfer_id' => null,
                    'is_parent' => true,
                    'subtransactions' => [
                        [
                            'id' => 'transaction-closed-split-food',
                            'account' => 'account-closed',
                            'date' => '2026-05-02',
                            'amount' => -3000,
                            'payee' => null,
                            'category' => 'category-food',
                            'transfer_id' => null,
                            'is_child' => true,
                            'parent_id' => 'transaction-closed-split',
                            'subtransactions' => [],
                        ],
                    ],
                ],
            ]]);
        }

        if (str_contains($path, '/accounts/account-closed-empty/transactions')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['data' => []]);
    });
}
