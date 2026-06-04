<?php

use App\Ai\Agents\FinancialAnalyst;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Models\FinancialAdvisorReport;
use App\Models\SavingsAccount;
use App\Services\FinancialAdvisorReportService;
use Carbon\CarbonImmutable;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    SavingsAccount::factory()->create(['parent_id' => null, 'amount' => '10000.00', 'apy' => '3.00']);
});

test('report service generates, persists, and returns a structured report', function () {
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
        ->and(FinancialAdvisorReport::count())->toBe(1)
        ->and($service->latest())->toBe($payload);

    FinancialAnalyst::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('tedensko'));
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

test('the command generates the report synchronously', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report', ['--sync' => true])
        ->assertSuccessful();

    expect(app(FinancialAdvisorReportService::class)->latest())->not->toBeNull();
});

test('the command queues report generation by default', function () {
    FinancialAnalyst::fake();

    $this->artisan('advisor:generate-report')->assertSuccessful();

    // Queue connection is sync in tests, so the report is generated immediately.
    expect(app(FinancialAdvisorReportService::class)->latest())->not->toBeNull();
});
