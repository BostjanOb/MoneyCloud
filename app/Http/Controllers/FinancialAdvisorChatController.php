<?php

namespace App\Http\Controllers;

use App\Ai\Agents\FinancialAdvisor;
use App\Services\ActualBudgetContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Symfony\Component\HttpFoundation\Response;

class FinancialAdvisorChatController extends Controller
{
    public function index(Request $request, ActualBudgetContextService $actualBudget): InertiaResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->latest('updated_at')
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (Conversation $conversation): array => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ])
            ->all();

        $activeConversationId = $this->resolveActiveConversation(
            $request->query('conversation'),
            $conversations,
        );

        return Inertia::render('Svetovalec/Klepet', [
            'conversations' => $conversations,
            'activeConversationId' => $activeConversationId,
            'messages' => $this->messagesFor($activeConversationId, $user->id),
            'actualBudget' => $actualBudget->metadata(),
        ]);
    }

    public function refreshActualBudget(ActualBudgetContextService $actualBudget): RedirectResponse
    {
        if (! $actualBudget->isConfigured()) {
            return back()->with('error', 'Actual Budget ni nastavljen.');
        }

        try {
            $actualBudget->refreshChatContext();

            return back()->with('status', 'Actual Budget podatki so osveženi.');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Actual Budget podatkov trenutno ni mogoče osvežiti.');
        }
    }

    public function stream(Request $request): Response
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $conversationId = $this->resolveOrCreateConversation(
            $validated['conversation_id'] ?? null,
            $validated['message'],
            $user->id,
        );

        $response = (new FinancialAdvisor)
            ->continue($conversationId, as: $user)
            ->stream($validated['message'])
            ->toResponse($request);

        $response->headers->set('X-Conversation-Id', $conversationId);
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');

        return $response;
    }

    /**
     * @param  array<int, array{id: string, title: string, updated_at: string|null}>  $conversations
     */
    private function resolveActiveConversation(?string $requested, array $conversations): ?string
    {
        if ($requested !== null && collect($conversations)->contains('id', $requested)) {
            return $requested;
        }

        return null;
    }

    /**
     * @return array<int, array{id: string, role: string, content: string, html: string|null}>
     */
    private function messagesFor(?string $conversationId, int $userId): array
    {
        if ($conversationId === null) {
            return [];
        }

        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'role', 'content'])
            ->map(fn (ConversationMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'html' => $message->role === 'assistant'
                    ? $this->renderMarkdown($message->content)
                    : null,
            ])
            ->all();
    }

    private function renderMarkdown(string $content): string
    {
        return Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function resolveOrCreateConversation(?string $conversationId, string $message, int $userId): string
    {
        $ownsConversation = $conversationId !== null
            && Conversation::query()
                ->whereKey($conversationId)
                ->where('user_id', $userId)
                ->exists();

        if ($ownsConversation) {
            return $conversationId;
        }

        return resolve(ConversationStore::class)
            ->storeConversation($userId, Str::limit($message, 60, ''));
    }
}
