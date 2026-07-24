<?php

use App\Enums\AdvisorModel;
use App\Jobs\GenerateFinancialAdvisorReport;
use App\Models\FinancialAdvisorReport;
use App\Models\User;
use App\Services\FinancialAdvisorReportService;
use Carbon\CarbonImmutable;
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

test('the latest stored report is passed to the page', function () {
    FinancialAdvisorReport::factory()->create([
        'generated_at' => CarbonImmutable::parse('2026-05-01 08:00:00'),
        'report' => [
            'povzetek' => 'Stara analiza',
            'ocena_neto_premozenja' => 'Stara ocena',
            'mocne_tocke' => [],
            'tveganja' => [],
            'priporocila' => [],
            'davcni_nasveti' => [],
            'naslednji_koraki' => [],
        ],
    ]);
    FinancialAdvisorReport::factory()->create([
        'generated_at' => CarbonImmutable::parse('2026-06-04 08:00:00'),
        'report' => [
            'povzetek' => 'Test povzetek',
            'ocena_neto_premozenja' => 'Test ocena',
            'mocne_tocke' => ['Dobra diverzifikacija'],
            'tveganja' => [],
            'priporocila' => [],
            'davcni_nasveti' => [],
            'naslednji_koraki' => ['Korak 1'],
        ],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('advisor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec')
            ->where('report.report.povzetek', 'Test povzetek')
            ->where('report.report.mocne_tocke.0', 'Dobra diverzifikacija')
        );
});

test('the history list and a specific report can be requested', function () {
    $older = FinancialAdvisorReport::factory()->create([
        'generated_at' => CarbonImmutable::parse('2026-05-01 08:00:00'),
        'model' => AdvisorModel::ClaudeSonnet46,
        'report' => ['povzetek' => 'Stara analiza'] + FinancialAdvisorReport::factory()->raw()['report'],
    ]);
    FinancialAdvisorReport::factory()->create([
        'generated_at' => CarbonImmutable::parse('2026-06-04 08:00:00'),
        'model' => AdvisorModel::Gpt54,
        'report' => ['povzetek' => 'Nova analiza'] + FinancialAdvisorReport::factory()->raw()['report'],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('advisor.index', ['report' => $older->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec')
            ->where('report.id', $older->id)
            ->where('report.report.povzetek', 'Stara analiza')
            ->where('report.model.value', 'claude-sonnet-4-6')
            ->where('report.model.label', 'Claude Sonnet 4.6')
            ->has('history', 2)
            ->where('history.0.model', 'GPT-5.4')
        );
});

test('generating dispatches the job and flags generation in progress', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.generate'))
        ->assertRedirect();

    Queue::assertPushed(
        GenerateFinancialAdvisorReport::class,
        fn (GenerateFinancialAdvisorReport $job) => $job->model === AdvisorModel::ClaudeSonnet46,
    );

    expect(app(FinancialAdvisorReportService::class)->isGenerating())->toBeTrue();
});

test('generating uses the selected model', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.generate'), ['model' => 'gpt-5.5'])
        ->assertRedirect();

    Queue::assertPushed(
        GenerateFinancialAdvisorReport::class,
        fn (GenerateFinancialAdvisorReport $job) => $job->model === AdvisorModel::Gpt55,
    );
});

test('generating rejects an invalid model', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.generate'), ['model' => 'gemini'])
        ->assertSessionHasErrors('model');

    Queue::assertNotPushed(GenerateFinancialAdvisorReport::class);
});

test('generating is skipped when already in progress', function () {
    Queue::fake();
    app(FinancialAdvisorReportService::class)->markGenerating();

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.generate'))
        ->assertRedirect();

    Queue::assertNotPushed(GenerateFinancialAdvisorReport::class);
});

test('generating dispatches the job only once for repeated requests', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('advisor.generate'))->assertRedirect();
    $this->actingAs($user)->post(route('advisor.generate'))->assertRedirect();

    Queue::assertPushed(GenerateFinancialAdvisorReport::class, 1);
});
