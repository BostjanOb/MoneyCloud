<?php

use App\Ai\Agents\FinancialAnalyst;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Models\SavingsAccount;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    SavingsAccount::factory()->create(['parent_id' => null, 'amount' => '10000.00', 'apy' => '3.00']);
});

test('report service generates, caches, and returns a structured report', function () {
    FinancialAnalyst::fake();

    $service = app(FinancialAdvisorReportService::class);
    $payload = $service->generate();

    expect($payload)->toHaveKeys(['generated_at', 'report'])
        ->and($payload['report'])->toHaveKeys([
            'povzetek',
            'ocena_neto_premozenja',
            'mocne_tocke',
            'tveganja',
            'priporocila',
            'davcni_nasveti',
            'naslednji_koraki',
        ])
        ->and($service->cached())->toBe($payload);

    FinancialAnalyst::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('tedensko'));
});

test('cached returns null before any report is generated', function () {
    expect(app(FinancialAdvisorReportService::class)->cached())->toBeNull();
});

test('clear removes the cached report', function () {
    FinancialAnalyst::fake();
    $service = app(FinancialAdvisorReportService::class);
    $service->generate();

    $service->clear();

    expect($service->cached())->toBeNull()
        ->and(Cache::has(FinancialAdvisorReportService::CACHE_KEY))->toBeFalse();
});

test('the queued job generates and caches the report', function () {
    FinancialAnalyst::fake();

    GenerateFinancialAdvisorReport::dispatchSync();

    expect(app(FinancialAdvisorReportService::class)->cached())->not->toBeNull();
});

test('the command generates the report synchronously', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report', ['--sync' => true])
        ->assertSuccessful();

    expect(app(FinancialAdvisorReportService::class)->cached())->not->toBeNull();
});

test('the command queues report generation by default', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report')->assertSuccessful();

    // Queue connection is sync in tests, so the report is generated immediately.
    expect(app(FinancialAdvisorReportService::class)->cached())->not->toBeNull();
});
