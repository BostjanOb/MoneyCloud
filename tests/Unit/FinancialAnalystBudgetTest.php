<?php

use App\Ai\Agents\FinancialAnalyst;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Services\FinancialAdvisorReportService;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;

/**
 * The analyst's budgets decide whether a report arrives complete. Too few
 * tokens truncates the structured payload mid-JSON and the whole analysis is
 * lost; too little time cuts the request off before the model has written
 * anything at all. Both have happened, so the ceilings are asserted here.
 *
 * @return object{value: int}
 */
function analystAttribute(string $attribute): object
{
    $attributes = (new ReflectionClass(FinancialAnalyst::class))->getAttributes($attribute);

    expect($attributes)->not->toBeEmpty();

    return $attributes[0]->newInstance();
}

test('the analyst has room for a full report plus reasoning in one step', function () {
    // A finished report is roughly 4.000 tokens of JSON, and a reasoning model
    // charges its thinking against the same per-step budget.
    expect(analystAttribute(MaxTokens::class)->value)->toBeGreaterThanOrEqual(16000);
});

test('the job outlives the agent request timeout', function () {
    // A job timeout at or below the request timeout kills the generation
    // before the agent itself can give up and report what went wrong.
    expect((new GenerateFinancialAdvisorReport)->timeout)
        ->toBeGreaterThan(analystAttribute(Timeout::class)->value);
});

test('the generating flag outlives the job', function () {
    // If the flag expires first, the UI drops out of its generating state and
    // a second run can be dispatched on top of the one still running.
    expect(FinancialAdvisorReportService::GENERATING_TTL_MINUTES * 60)
        ->toBeGreaterThan((new GenerateFinancialAdvisorReport)->timeout);
});
