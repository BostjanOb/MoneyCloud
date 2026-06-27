<?php

use App\Ai\Agents\FinancialAnalyst;
use App\Enums\AdvisorModel;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Models\FinancialAdvisorReport;
use App\Models\SavingsAccount;
use App\Services\ActualBudgetContextService;
use App\Services\FinancialAdvisorReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    config([
        'services.actual_budget.api_key' => null,
        'services.actual_budget.budget_sync_id' => null,
    ]);

    SavingsAccount::factory()->create(['parent_id' => null, 'amount' => '10000.00', 'apy' => '3.00']);
});

test('report service generates, persists, and returns a structured report', function () {
    FinancialAnalyst::fake();

    $service = app(FinancialAdvisorReportService::class);
    $payload = $service->generate();

    expect($payload)->toHaveKeys(['id', 'generated_at', 'model', 'usage', 'report'])
        ->and($payload['model']['value'])->toBe('claude-sonnet-4-6')
        ->and($payload['model']['label'])->toBe('Claude Sonnet 4.6')
        ->and($payload['report'])->toHaveKeys([
            'povzetek',
            'ocena_neto_premozenja',
            'mocne_tocke',
            'tveganja',
            'priporocila',
            'davcni_nasveti',
            'naslednji_koraki',
        ])
        ->and(FinancialAdvisorReport::count())->toBe(1)
        ->and($service->latest())->toBe($payload);

    FinancialAnalyst::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('tedensko'));
});

test('report skips actual budget when it is not configured', function () {
    FinancialAnalyst::fake();
    Http::fake();

    app(FinancialAdvisorReportService::class)->generate();

    Http::assertNothingSent();
});

test('latest returns null before any report is generated', function () {
    expect(app(FinancialAdvisorReportService::class)->latest())->toBeNull();
});

test('latest returns only the most recently generated report', function () {
    $older = FinancialAdvisorReport::factory()->create([
        'generated_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
        'report' => ['povzetek' => 'Stara analiza'] + FinancialAdvisorReport::factory()->raw()['report'],
    ]);
    $newer = FinancialAdvisorReport::factory()->create([
        'generated_at' => CarbonImmutable::parse('2026-06-01 10:00:00'),
        'report' => ['povzetek' => 'Nova analiza'] + FinancialAdvisorReport::factory()->raw()['report'],
    ]);

    $payload = app(FinancialAdvisorReportService::class)->latest();

    expect($payload['report']['povzetek'])->toBe('Nova analiza');
});

test('generating keeps prior reports, persisting a new latest one', function () {
    FinancialAnalyst::fake();
    $service = app(FinancialAdvisorReportService::class);

    $service->generate();
    $service->generate();

    expect(FinancialAdvisorReport::count())->toBe(2);
});

test('the queued job generates and persists the report', function () {
    FinancialAnalyst::fake();

    GenerateFinancialAdvisorReport::dispatchSync();

    expect(app(FinancialAdvisorReportService::class)->latest())->not->toBeNull();
});

test('the report stores the chosen model', function () {
    FinancialAnalyst::fake();

    app(FinancialAdvisorReportService::class)->generate(AdvisorModel::Gpt54);

    expect(FinancialAdvisorReport::latestFirst()->first()->model)
        ->toBe(AdvisorModel::Gpt54);
});

test('the report stores token usage from the response', function () {
    FinancialAnalyst::fake();

    app(FinancialAdvisorReportService::class)->generate();

    $report = FinancialAdvisorReport::latestFirst()->first();

    expect($report->usage)->toBeArray()
        ->toHaveKeys(['prompt_tokens', 'completion_tokens']);
});

test('the command generates the report synchronously', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report', ['--sync' => true])
        ->assertSuccessful();

    expect(app(FinancialAdvisorReportService::class)->latest())->not->toBeNull();
});

test('the command generates with the selected model', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report', ['--sync' => true, '--model' => 'gpt-5.4'])
        ->assertSuccessful();

    expect(FinancialAdvisorReport::latestFirst()->first()->model)
        ->toBe(AdvisorModel::Gpt54);
});

test('the command rejects an invalid model', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report', ['--sync' => true, '--model' => 'gemini'])
        ->assertFailed();

    expect(FinancialAdvisorReport::count())->toBe(0);
});

test('the command queues report generation by default', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report')->assertSuccessful();

    // Queue connection is sync in tests, so the report is generated immediately.
    expect(app(FinancialAdvisorReportService::class)->latest())->not->toBeNull();
});

test('report stores stale actual budget warning when live data is unavailable', function () {
    FinancialAnalyst::fake();
    config([
        'services.actual_budget.api_key' => 'test-key',
        'services.actual_budget.base_url' => 'https://money-api.test/v1',
        'services.actual_budget.budget_sync_id' => 'budget-sync-id',
    ]);
    Cache::forever(ActualBudgetContextService::CACHE_KEY, [
        'available' => true,
        'source' => 'cache',
        'generated_at' => now()->toIso8601String(),
        'window' => ['days' => 90, 'since' => now()->subDays(90)->toDateString(), 'until' => now()->toDateString()],
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

    $payload = app(FinancialAdvisorReportService::class)->generate();

    expect($payload['report']['opozorila'])->toContain(ActualBudgetContextService::STALE_WARNING);

    FinancialAnalyst::assertPrompted(fn (AgentPrompt $prompt) => $prompt
        ->contains('Actual Budget je nastavljen za to poročilo')
        && $prompt->contains('raw transakcije')
        && $prompt->contains('ne analiziraj samo iz MoneyCloud'));
});
