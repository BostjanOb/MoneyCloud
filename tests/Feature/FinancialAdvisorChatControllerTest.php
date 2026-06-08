<?php

use App\Models\User;
use App\Services\ActualBudgetContextService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\ConversationMessage;

beforeEach(function () {
    config([
        'services.actual_budget.api_key' => null,
        'services.actual_budget.budget_sync_id' => null,
    ]);
});

test('guests are redirected to the login page', function () {
    $this->get(route('advisor.chat'))->assertRedirect(route('login'));
});

test('opening the chat defaults to a new conversation', function () {
    $user = User::factory()->create();
    resolve(ConversationStore::class)->storeConversation($user->id, 'Obstojeci pogovor');

    $this->actingAs($user)
        ->get(route('advisor.chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec/Klepet')
            ->where('activeConversationId', null)
            ->where('messages', [])
            ->where('actualBudget.configured', false)
            ->where('actualBudget.available', false)
            ->has('conversations', 1)
        );
});

test('an explicitly requested conversation is selected', function () {
    $user = User::factory()->create();
    $conversationId = resolve(ConversationStore::class)
        ->storeConversation($user->id, 'Obstojeci pogovor');

    $this->actingAs($user)
        ->get(route('advisor.chat', ['conversation' => $conversationId]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec/Klepet')
            ->where('activeConversationId', $conversationId)
        );
});

test('assistant messages are rendered from markdown to html', function () {
    $user = User::factory()->create();
    $conversationId = resolve(ConversationStore::class)
        ->storeConversation($user->id, 'Pogovor');

    ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'agent' => 'financial-advisor',
        'role' => 'assistant',
        'content' => "Predlogi:\n\n- **Diverzificiraj**\n- Zmanjšaj gotovino",
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    $this->actingAs($user)
        ->get(route('advisor.chat', ['conversation' => $conversationId]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec/Klepet')
            ->where('messages.0.html', fn (string $html): bool => str_contains($html, '<ul>')
                && str_contains($html, '<strong>Diverzificiraj</strong>'))
        );
});

test('an unknown conversation falls back to a new conversation', function () {
    $user = User::factory()->create();
    resolve(ConversationStore::class)->storeConversation($user->id, 'Obstojeci pogovor');

    $this->actingAs($user)
        ->get(route('advisor.chat', ['conversation' => 'unknown-id']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec/Klepet')
            ->where('activeConversationId', null)
        );
});

test('refreshing actual budget updates chat metadata cache', function () {
    config([
        'services.actual_budget.api_key' => 'test-key',
        'services.actual_budget.base_url' => 'https://money-api.test/v1',
        'services.actual_budget.budget_sync_id' => 'budget-sync-id',
        'services.actual_budget.transaction_page_size' => 50,
    ]);
    Cache::forget(ActualBudgetContextService::CACHE_KEY);
    Http::fake([
        'https://money-api.test/v1/budgets/budget-sync-id/accounts' => Http::response(['data' => [
            ['id' => 'account-1', 'name' => 'TRR', 'offbudget' => false, 'closed' => false],
        ]]),
        'https://money-api.test/v1/budgets/budget-sync-id/categorygroups' => Http::response(['data' => [
            ['id' => 'group-1', 'name' => 'Hrana', 'is_income' => false, 'hidden' => false],
        ]]),
        'https://money-api.test/v1/budgets/budget-sync-id/categories' => Http::response(['data' => [
            ['id' => 'category-1', 'name' => 'Živila', 'group_id' => 'group-1', 'is_income' => false, 'hidden' => false],
        ]]),
        'https://money-api.test/v1/budgets/budget-sync-id/payees' => Http::response(['data' => [
            ['id' => 'payee-1', 'name' => 'Mercator'],
        ]]),
        'https://money-api.test/v1/budgets/budget-sync-id/months/*' => Http::response(['data' => [
            'month' => '2026-06',
            'incomeAvailable' => 0,
            'totalBudgeted' => 0,
            'totalIncome' => 0,
            'totalSpent' => 0,
            'totalBalance' => 0,
            'categoryGroups' => [],
        ]]),
        'https://money-api.test/v1/budgets/budget-sync-id/accounts/account-1/transactions*' => Http::response(['data' => [
            [
                'id' => 'transaction-1',
                'account' => 'account-1',
                'date' => '2026-06-01',
                'amount' => -1234,
                'payee' => 'payee-1',
                'category' => 'category-1',
                'transfer_id' => null,
                'subtransactions' => [],
            ],
        ]]),
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.chat.actual-budget.refresh'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Actual Budget podatki so osveženi.');

    $this->actingAs(User::factory()->create())
        ->get(route('advisor.chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('actualBudget.available', true)
            ->where('actualBudget.transaction_count', 1)
        );
});

test('refreshing actual budget does nothing when actual is not configured', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('advisor.chat.actual-budget.refresh'))
        ->assertRedirect()
        ->assertSessionHas('error', 'Actual Budget ni nastavljen.');

    Http::assertNothingSent();
});
