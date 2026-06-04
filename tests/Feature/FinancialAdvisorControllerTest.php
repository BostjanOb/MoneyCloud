<?php

use App\Jobs\GenerateFinancialAdvisorReport;
use App\Models\User;
use App\Services\FinancialAdvisorReportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('advisor.index'))->assertRedirect(route('login'));
});

test('authenticated users see an empty advisor state', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('advisor.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec')
            ->where('report', null)
            ->where('isGenerating', false)
        );
});

test('a cached report is passed to the page', function () {
    $payload = [
        'generated_at' => '2026-06-04T08:00:00+02:00',
        'report' => [
            'povzetek' => 'Test povzetek',
            'ocena_neto_premozenja' => 'Test ocena',
            'mocne_tocke' => ['Dobra diverzifikacija'],
            'tveganja' => [],
            'priporocila' => [],
            'davcni_nasveti' => [],
            'naslednji_koraki' => ['Korak 1'],
        ],
    ];

    Cache::forever(FinancialAdvisorReportService::CACHE_KEY, $payload);

    $this->actingAs(User::factory()->create())
        ->get(route('advisor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec')
            ->where('report.report.povzetek', 'Test povzetek')
            ->where('report.report.mocne_tocke.0', 'Dobra diverzifikacija')
        );
});

test('generating dispatches the job and flags generation in progress', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.generate'))
        ->assertRedirect();

    Queue::assertPushed(GenerateFinancialAdvisorReport::class);

    expect(app(FinancialAdvisorReportService::class)->isGenerating())->toBeTrue();
});

test('generating is skipped when already in progress', function () {
    Queue::fake();
    app(FinancialAdvisorReportService::class)->markGenerating();

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.generate'))
        ->assertRedirect();

    Queue::assertNotPushed(GenerateFinancialAdvisorReport::class);
});
