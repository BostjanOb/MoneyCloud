<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, SendHorizontal, Sparkles } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import {
    index as advisorChatIndex,
    stream as advisorChatStream,
} from '@/actions/App/Http/Controllers/FinancialAdvisorChatController';
import { index as advisorIndex } from '@/actions/App/Http/Controllers/FinancialAdvisorController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Conversation = {
    id: string;
    title: string;
    updated_at: string | null;
};

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    html?: string | null;
};

const props = defineProps<{
    conversations: Conversation[];
    activeConversationId: string | null;
    messages: ChatMessage[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Svetovalec',
                href: advisorIndex.url(),
            },
            {
                title: 'Klepet',
                href: advisorChatIndex.url(),
            },
        ],
    },
});

let localId = 0;
const nextLocalId = (): string => `local-${localId++}`;

const localMessages = ref<ChatMessage[]>(
    props.messages.map((message) => ({ ...message })),
);
const localConversations = ref<Conversation[]>(
    props.conversations.map((conversation) => ({ ...conversation })),
);
const currentConversationId = ref<string | null>(props.activeConversationId);
const input = ref('');
const streaming = ref(false);
const thread = ref<HTMLElement | null>(null);

// Inertia reuses this page component across visits, so the local state must be
// re-synced from props whenever navigation returns a fresh set of messages
// (switching conversations, opening a new chat, or reloading after a reply).
watch(
    () => props.messages,
    () => {
        currentConversationId.value = props.activeConversationId;
        localMessages.value = props.messages.map((message) => ({ ...message }));
        localConversations.value = props.conversations.map((conversation) => ({
            ...conversation,
        }));
        void scrollToBottom();
    },
);

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function scrollToBottom(): Promise<void> {
    await nextTick();

    if (thread.value) {
        thread.value.scrollTop = thread.value.scrollHeight;
    }
}

watch(() => localMessages.value.at(-1)?.content, scrollToBottom);

async function send(): Promise<void> {
    const message = input.value.trim();

    if (message === '' || streaming.value) {
        return;
    }

    input.value = '';
    streaming.value = true;

    localMessages.value.push({
        id: nextLocalId(),
        role: 'user',
        content: message,
    });

    const assistant = ref<ChatMessage>({
        id: nextLocalId(),
        role: 'assistant',
        content: '',
    });
    localMessages.value.push(assistant.value);
    await scrollToBottom();

    try {
        const response = await fetch(advisorChatStream.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                message,
                conversation_id: currentConversationId.value,
            }),
        });

        if (!response.ok || !response.body) {
            throw new Error('Napaka pri komunikaciji s svetovalcem.');
        }

        const conversationId = response.headers.get('X-Conversation-Id');

        if (conversationId) {
            registerConversation(conversationId, message);
        }

        await consumeStream(response.body, assistant);

        // Re-fetch the persisted thread so the streamed reply is replaced by the
        // server-rendered markdown (HTML) version.
        if (currentConversationId.value) {
            router.get(
                advisorChatIndex.url({
                    query: { conversation: currentConversationId.value },
                }),
                {},
                {
                    only: [
                        'messages',
                        'conversations',
                        'activeConversationId',
                    ],
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }
    } catch (error) {
        assistant.value.content =
            error instanceof Error
                ? error.message
                : 'Prišlo je do napake. Poskusite znova.';
    } finally {
        streaming.value = false;
        await scrollToBottom();
    }
}

async function consumeStream(
    body: ReadableStream<Uint8Array>,
    assistant: { value: ChatMessage },
): Promise<void> {
    const reader = body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });

        const chunks = buffer.split('\n\n');
        buffer = chunks.pop() ?? '';

        for (const chunk of chunks) {
            const line = chunk.replace(/^data: ?/, '').trim();

            if (line === '' || line === '[DONE]') {
                continue;
            }

            try {
                const event = JSON.parse(line);

                if (
                    event.type === 'text_delta' &&
                    typeof event.delta === 'string'
                ) {
                    assistant.value.content += event.delta;
                }
            } catch {
                // Ignore non-JSON keepalive lines.
            }
        }
    }
}

function registerConversation(id: string, message: string): void {
    currentConversationId.value = id;

    if (
        localConversations.value.some((conversation) => conversation.id === id)
    ) {
        return;
    }

    localConversations.value.unshift({
        id,
        title: message.slice(0, 60),
        updated_at: new Date().toISOString(),
    });
}
</script>

<template>
    <Head title="Klepet s svetovalcem" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                title="Klepet s svetovalcem"
                description="Vprašaj karkoli o premoženju, prejemkih in naložbah gospodinjstva"
            />
            <Button as-child variant="outline" size="sm">
                <Link :href="advisorChatIndex.url()">
                    <Plus class="size-4" />
                    Nov pogovor
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
            <!-- Conversation list -->
            <aside class="hidden lg:block">
                <div class="flex flex-col gap-1">
                    <Link
                        v-for="conversation in localConversations"
                        :key="conversation.id"
                        :href="
                            advisorChatIndex.url({
                                query: { conversation: conversation.id },
                            })
                        "
                        :class="
                            cn(
                                'truncate rounded-md px-3 py-2 text-sm transition-colors hover:bg-accent',
                                conversation.id === currentConversationId &&
                                    'bg-accent font-medium',
                            )
                        "
                    >
                        {{ conversation.title }}
                    </Link>
                    <p
                        v-if="localConversations.length === 0"
                        class="px-3 py-2 text-sm text-muted-foreground"
                    >
                        Ni še pogovorov.
                    </p>
                </div>
            </aside>

            <!-- Thread -->
            <div
                class="flex h-[calc(100vh-16rem)] flex-col rounded-xl border bg-card"
            >
                <div ref="thread" class="flex-1 space-y-4 overflow-y-auto p-4">
                    <div
                        v-if="localMessages.length === 0"
                        class="flex h-full flex-col items-center justify-center gap-3 text-center"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-full bg-primary/10"
                        >
                            <Sparkles class="size-6 text-primary" />
                        </div>
                        <p class="max-w-sm text-sm text-muted-foreground">
                            Vprašaj npr. »Kako je razporejeno moje premoženje?«
                            ali »Kje imam neaktivno gotovino?«
                        </p>
                    </div>

                    <div
                        v-for="message in localMessages"
                        :key="message.id"
                        :class="
                            cn(
                                'flex',
                                message.role === 'user'
                                    ? 'justify-end'
                                    : 'justify-start',
                            )
                        "
                    >
                        <div
                            :class="
                                cn(
                                    'max-w-[80%] rounded-2xl px-4 py-2 text-sm',
                                    message.role === 'user'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted',
                                )
                            "
                        >
                            <div
                                v-if="message.html"
                                class="prose prose-sm dark:prose-invert max-w-none prose-pre:bg-foreground/10 prose-pre:text-foreground"
                                v-html="message.html"
                            />
                            <span v-else class="whitespace-pre-wrap">{{
                                message.content
                            }}</span>
                            <span
                                v-if="
                                    streaming &&
                                    message.role === 'assistant' &&
                                    message.content === ''
                                "
                                class="text-muted-foreground"
                            >
                                …
                            </span>
                        </div>
                    </div>
                </div>

                <form
                    class="flex items-end gap-2 border-t p-3"
                    @submit.prevent="send"
                >
                    <textarea
                        v-model="input"
                        rows="1"
                        placeholder="Napiši sporočilo …"
                        class="max-h-32 min-h-10 flex-1 resize-none rounded-md border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @keydown.enter.exact.prevent="send"
                    />
                    <Button type="submit" size="icon" :disabled="streaming">
                        <SendHorizontal class="size-4" />
                    </Button>
                </form>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Informativni nasveti za osebno rabo in ne licencirano finančno
            svetovanje.
        </p>
    </div>
</template>
