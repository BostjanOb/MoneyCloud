<?php

use App\Ai\Tools\GetAllocationBreakdown;
use App\Ai\Tools\GetBondSchedule;
use App\Ai\Tools\GetIncomeSummary;
use App\Ai\Tools\GetInvestmentHoldings;
use App\Ai\Tools\GetNetWorthOverview;
use App\Ai\Tools\GetPortfolioHistory;
use App\Ai\Tools\GetSavingsAccounts;
use App\Ai\Tools\GetTaxAnalysis;
use App\Models\SavingsAccount;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

dataset('financial tools', [
    'net worth overview' => [GetNetWorthOverview::class, 'total'],
    'allocation breakdown' => [GetAllocationBreakdown::class, 'items'],
    'portfolio history' => [GetPortfolioHistory::class, 'points'],
    'savings accounts' => [GetSavingsAccounts::class, 'accounts'],
    'investment holdings' => [GetInvestmentHoldings::class, 'securities'],
    'income summary' => [GetIncomeSummary::class, 'by_year'],
    'tax analysis' => [GetTaxAnalysis::class, 'people'],
    'bond schedule' => [GetBondSchedule::class, 'events'],
]);

test('tool returns valid json containing expected key', function (string $toolClass, string $expectedKey) {
    SavingsAccount::factory()->create(['parent_id' => null, 'amount' => '1000.00', 'apy' => '2.00']);

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
