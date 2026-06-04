<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Contracts\ConversationStore;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;

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
