<?php

use App\Ai\Agents\FinancialAdvisor;
use App\Ai\Agents\FinancialAnalyst;
use App\Ai\Tools\GetActualBudgetOverview;
use App\Ai\Tools\GetActualSpendingByCategory;
use App\Ai\Tools\GetActualTransactions;
use App\Ai\Tools\GetAllocationBreakdown;
use App\Ai\Tools\GetBondSchedule;
use App\Ai\Tools\GetIncomeSummary;
use App\Ai\Tools\GetInvestmentHoldings;
use App\Ai\Tools\GetNetWorthOverview;
use App\Ai\Tools\GetPortfolioHistory;
use App\Ai\Tools\GetSavingsAccounts;
use App\Ai\Tools\GetTaxAnalysis;
use App\Ai\Tools\RefreshActualChatContext;
use App\Models\SavingsAccount;
use App\Services\ActualBudgetContextService;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    config([
        'services.actual_budget.api_key' => null,
        'services.actual_budget.budget_sync_id' => null,
    ]);
});

dataset('financial tools', [
    'net worth overview' => [GetNetWorthOverview::class, 'total'],
    'allocation breakdown' => [GetAllocationBreakdown::class, 'items'],
    'portfolio history' => [GetPortfolioHistory::class, 'points'],
    'savings accounts' => [GetSavingsAccounts::class, 'accounts'],
    'investment holdings' => [GetInvestmentHoldings::class, 'securities'],
    'income summary' => [GetIncomeSummary::class, 'by_year'],
    'tax analysis' => [GetTaxAnalysis::class, 'people'],
    'bond schedule' => [GetBondSchedule::class, 'events'],
    'actual budget overview' => [GetActualBudgetOverview::class, 'budget_months'],
    'actual spending by category' => [GetActualSpendingByCategory::class, 'categories'],
    'actual transactions' => [GetActualTransactions::class, 'transactions'],
]);

test('tool returns valid json containing expected key', function (string $toolClass, string $expectedKey) {
    SavingsAccount::factory()->create(['parent_id' => null, 'amount' => '1000.00', 'apy' => '2.00']);
    Cache::forever(ActualBudgetContextService::CACHE_KEY, [
        'available' => true,
        'source' => 'cache',
        'generated_at' => now()->toIso8601String(),
        'window' => ['days' => 90, 'since' => now()->subDays(90)->toDateString(), 'until' => now()->toDateString()],
        'warnings' => [],
        'budget_months' => [],
        'transactions' => [],
    ]);

    /** @var Tool $tool */
    $tool = app($toolClass);

    $result = (string) $tool->handle(new Request);
    $decoded = json_decode($result, true);

    expect($tool)->toBeInstanceOf(Tool::class)
        ->and(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded)->toBeArray()
        ->and($decoded)->toHaveKey($expectedKey)
        ->and($decoded)->toHaveKey('currency');
})->with('financial tools');

test('portfolio history tool respects months argument', function () {
    $result = (string) app(GetPortfolioHistory::class)->handle(new Request(['months' => 6]));
    $decoded = json_decode($result, true);

    expect($decoded)->toHaveKey('points')
        ->and($decoded['months'])->toBe(0); // no snapshots seeded
});

test('advisor agents only expose actual budget tools when configured', function () {
    $advisorTools = collect((new FinancialAdvisor)->tools())->map(fn (object $tool): string => $tool::class);
    $analystTools = collect((new FinancialAnalyst)->tools())->map(fn (object $tool): string => $tool::class);

    expect($advisorTools)->not->toContain(GetActualBudgetOverview::class)
        ->and($advisorTools)->not->toContain(GetActualTransactions::class)
        ->and($advisorTools)->not->toContain(RefreshActualChatContext::class)
        ->and($analystTools)->not->toContain(GetActualBudgetOverview::class)
        ->and($analystTools)->not->toContain(GetActualTransactions::class);

    config([
        'services.actual_budget.api_key' => 'test-key',
        'services.actual_budget.budget_sync_id' => 'budget-sync-id',
    ]);

    $configuredAdvisorTools = collect((new FinancialAdvisor)->tools())->map(fn (object $tool): string => $tool::class);
    $configuredAnalystTools = collect((new FinancialAnalyst)->tools())->map(fn (object $tool): string => $tool::class);

    expect($configuredAdvisorTools)->toContain(GetActualBudgetOverview::class)
        ->and($configuredAdvisorTools)->toContain(GetActualSpendingByCategory::class)
        ->and($configuredAdvisorTools)->toContain(GetActualTransactions::class)
        ->and($configuredAdvisorTools)->toContain(RefreshActualChatContext::class)
        ->and($configuredAnalystTools)->toContain(GetActualBudgetOverview::class)
        ->and($configuredAnalystTools)->toContain(GetActualSpendingByCategory::class)
        ->and($configuredAnalystTools)->toContain(GetActualTransactions::class)
        ->and($configuredAnalystTools)->not->toContain(RefreshActualChatContext::class);
});
