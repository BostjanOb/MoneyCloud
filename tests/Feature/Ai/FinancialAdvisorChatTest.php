<?php

use App\Ai\Agents\FinancialAdvisor;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Storage\DatabaseConversationStore;

function makeConversation(User $user, string $title = 'Pogovor'): Conversation
{
    return Conversation::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'title' => $title,
    ]);
}

function addMessage(Conversation $conversation, string $role, string $content, int $secondsOffset = 0): void
{
    ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversation->id,
        'user_id' => $conversation->user_id,
        'agent' => FinancialAdvisor::class,
        'role' => $role,
        'content' => $content,
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'created_at' => now()->addSeconds($secondsOffset),
        'updated_at' => now()->addSeconds($secondsOffset),
    ]);
}

test('guests are redirected from the chat page', function () {
    $this->get(route('advisor.chat'))->assertRedirect(route('login'));
});

test('chat page renders an empty state', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('advisor.chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec/Klepet')
            ->where('conversations', [])
            ->where('activeConversationId', null)
            ->where('messages', [])
        );
});

test('chat page renders the active conversation messages', function () {
    $user = User::factory()->create();
    $conversation = makeConversation($user, 'Moje premoženje');
    addMessage($conversation, 'user', 'Kako je razporejeno premoženje?', 0);
    addMessage($conversation, 'assistant', 'Razporeditev je naslednja …', 1);

    $this->actingAs($user)
        ->get(route('advisor.chat', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Svetovalec/Klepet')
            ->where('activeConversationId', $conversation->id)
            ->has('conversations', 1)
            ->has('messages', 2)
            ->where('messages.0.role', 'user')
            ->where('messages.1.content', 'Razporeditev je naslednja …')
        );
});

test('only the owner can view a conversation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = makeConversation($owner);

    $this->actingAs($other)
        ->get(route('advisor.chat', ['conversation' => $conversation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeConversationId', null)
            ->where('messages', [])
        );
});

test('streaming a message creates a conversation and returns its id', function () {
    FinancialAdvisor::fake(['Pozdravljeni, takole je videti vaše premoženje.']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('advisor.chat.stream'), [
        'message' => 'Kako mi gre?',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($response->headers->get('X-Conversation-Id'))->not->toBeNull()
        ->and($content)->toContain('text_delta')
        ->and(Conversation::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('streaming continues an existing conversation without creating a new one', function () {
    FinancialAdvisor::fake(['Nadaljujem pogovor.']);
    $user = User::factory()->create();
    $conversation = makeConversation($user);

    $response = $this->actingAs($user)->post(route('advisor.chat.stream'), [
        'message' => 'Še eno vprašanje.',
        'conversation_id' => $conversation->id,
    ]);

    $response->assertOk();
    $response->streamedContent();

    expect($response->headers->get('X-Conversation-Id'))->toBe($conversation->id)
        ->and(Conversation::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('streaming requires a message', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('advisor.chat.stream'), [])
        ->assertSessionHasErrors('message');
});

test('streaming persists replies larger than the legacy TEXT column limit', function () {
    // > 64 KB; would truncate (and throw) against the original TEXT columns.
    $largeReply = str_repeat('Premoženje gospodinjstva. ', 3000);
    FinancialAdvisor::fake([$largeReply]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('advisor.chat.stream'), [
        'message' => 'Povzemi vse.',
    ]);

    $response->assertOk();
    $response->streamedContent();

    $stored = ConversationMessage::query()
        ->where('user_id', $user->id)
        ->where('role', 'assistant')
        ->first();

    expect($stored)->not->toBeNull()
        ->and(strlen((string) $stored->content))->toBeGreaterThan(65535);
});

test('a persistence failure yields a graceful error event instead of a fatal', function () {
    FinancialAdvisor::fake(['Pozdravljeni.']);
    $user = User::factory()->create();

    // Force the assistant message persistence (which runs after the SSE body is
    // flushed) to fail, mirroring the production "Data too long" exception.
    $this->app->bind(ConversationStore::class, fn () => new class extends DatabaseConversationStore
    {
        public function storeAssistantMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt, AgentResponse $response): string
        {
            throw new RuntimeException('Persistence boom.');
        }
    });

    $response = $this->actingAs($user)->post(route('advisor.chat.stream'), [
        'message' => 'Kako mi gre?',
    ]);

    $response->assertOk();

    expect($response->streamedContent())
        ->toContain('"type":"error"')
        ->toContain('[DONE]');
});
