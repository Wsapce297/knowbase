<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FileText,
    LoaderCircle,
    MessageSquarePlus,
    SendHorizontal,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import ConversationController from '@/actions/App/Http/Controllers/Chat/ConversationController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ChatRequestError, streamChat } from '@/lib/chatStream';
import type {
    ChatMessage,
    ChatSource,
    ConversationInfo,
    ConversationSummary,
} from '@/types/chat';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Chat',
                href: ConversationController.index(),
            },
        ],
    },
});

const props = defineProps<{
    conversations: ConversationSummary[];
    conversation: ConversationInfo | null;
    messages: ChatMessage[];
    maxQuestionLength: number;
}>();

/*
 * Local copy of the messages, so new ones can appear before the server
 * confirms them. Refreshed whenever the server sends new props.
 */
const messages = ref<ChatMessage[]>([...props.messages]);
const conversationId = ref<number | null>(props.conversation?.id ?? null);

const question = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const bottom = ref<HTMLElement | null>(null);

watch(
    () => props.messages,
    (fresh) => {
        if (!sending.value) {
            messages.value = [...fresh];
        }
    },
);

watch(
    () => props.conversation,
    (fresh) => (conversationId.value = fresh?.id ?? null),
);

function scrollToBottom(): void {
    void nextTick(() => bottom.value?.scrollIntoView({ block: 'end' }));
}

onMounted(scrollToBottom);

/*
 * Sending a question and showing the answer while it streams in.
 */
async function send(): Promise<void> {
    const text = question.value.trim();

    if (text === '' || sending.value) {
        return;
    }

    sending.value = true;
    error.value = null;
    question.value = '';

    // Show the question and an empty answer right away (temporary negative ids).
    const now = new Date().toISOString();
    messages.value.push(
        { id: -1, role: 'user', content: text, sources: [], createdAt: now },
        { id: -2, role: 'assistant', content: '', sources: [], createdAt: now },
    );
    const draft = messages.value[messages.value.length - 1]!;
    scrollToBottom();

    // "as" keeps TypeScript from assuming these stay null (they change inside callbacks).
    let failed = null as string | null;
    let finishedConversation = null as number | null;

    try {
        await streamChat(
            { question: text, conversation_id: conversationId.value },
            (event) => {
                if (event.type === 'text') {
                    draft.content += event.text;
                    scrollToBottom();
                } else if (event.type === 'done') {
                    Object.assign(draft, event.message);
                    finishedConversation = event.conversationId;
                } else {
                    failed = event.message;
                }
            },
        );
    } catch (e) {
        failed =
            e instanceof ChatRequestError
                ? e.message
                : 'The connection was lost. Please try again.';
    }

    sending.value = false;

    if (failed !== null || finishedConversation === null) {
        // Take the question back out and put it in the box, so it can be sent again.
        messages.value = messages.value.filter((m) => m.id > 0);
        question.value = text;
        error.value = failed ?? 'The answer was cut off. Please try again.';

        return;
    }

    // Load the saved messages, the URL of the conversation and the updated list.
     router.visit(ConversationController.show.url(finishedConversation), {
        preserveScroll: true,
        preserveState: true,
        showProgress: false, // a quiet background refresh, not a page change
        only: ['conversations', 'conversation', 'messages'],
        onSuccess: scrollToBottom,
    });
}

const remaining = computed(
    () => props.maxQuestionLength - question.value.length,
);

/*
 * Answers contain citations like [1] or [1, 3]. Split the text so each
 * citation can be shown as a button that opens its source.
 */
type Part =
    | { type: 'text'; text: string }
    | { type: 'cite'; numbers: number[] };

function parts(content: string): Part[] {
    const result: Part[] = [];
    let last = 0;

    for (const match of content.matchAll(/\[(\d+(?:\s*,\s*\d+)*)\]/g)) {
        const index = match.index ?? 0;

        if (index > last) {
            result.push({ type: 'text', text: content.slice(last, index) });
        }

        result.push({
            type: 'cite',
            numbers: (match[1] ?? '').split(',').map((n) => Number(n.trim())),
        });

        last = index + match[0].length;
    }

    if (last < content.length) {
        result.push({ type: 'text', text: content.slice(last) });
    }

    return result;
}

function sourceFor(
    message: ChatMessage,
    number: number,
): ChatSource | undefined {
    return message.sources.find((source) => source.number === number);
}

function citedSources(message: ChatMessage): ChatSource[] {
    return message.sources.filter((source) => source.cited);
}

// Which source box is open, as "messageId-number".
const openSource = ref<string | null>(null);

function toggleSource(message: ChatMessage, number: number): void {
    const key = `${message.id}-${number}`;
    openSource.value = openSource.value === key ? null : key;
}

function openSourceOf(message: ChatMessage): ChatSource | undefined {
    if (!openSource.value?.startsWith(`${message.id}-`)) {
        return undefined;
    }

    return sourceFor(message, Number(openSource.value.split('-').pop()));
}

function sourceLabel(source: ChatSource): string {
    return source.page !== null
        ? `${source.documentTitle}, page ${source.page}`
        : source.documentTitle;
}

/*
 * Deleting the open conversation.
 */
const confirmingDelete = ref(false);

function deleteConversation(): void {
    if (props.conversation === null) {
        return;
    }

    router.delete(ConversationController.destroy.url(props.conversation.id), {
        onFinish: () => (confirmingDelete.value = false),
    });
}
</script>

<template>
    <Head :title="conversation?.title ?? 'Chat'" />

    <div class="flex flex-1 gap-6 p-4">
        <!-- Conversation list -->
        <aside
            class="hidden w-64 shrink-0 md:sticky md:top-4 md:flex md:max-h-[calc(100svh-6rem)] md:flex-col md:gap-2 md:self-start"
        >
            <Button as-child variant="outline" class="justify-start">
                <Link :href="ConversationController.index()">
                    <MessageSquarePlus />
                    New chat
                </Link>
            </Button>

            <nav class="flex flex-col gap-1 overflow-y-auto">
                <Link
                    v-for="item in conversations"
                    :key="item.id"
                    :href="ConversationController.show(item.id)"
                    class="rounded-md px-3 py-2 text-sm hover:bg-muted"
                    :class="{
                        'bg-muted font-medium': item.id === conversation?.id,
                    }"
                >
                    <span class="block truncate">{{ item.title }}</span>
                    <span class="block text-xs text-muted-foreground">{{
                        item.updatedAt
                    }}</span>
                </Link>

                <p
                    v-if="conversations.length === 0"
                    class="px-3 py-2 text-sm text-muted-foreground"
                >
                    Your conversations will appear here.
                </p>
            </nav>
        </aside>

        <!-- Messages and question box -->
        <section class="flex min-w-0 flex-1 flex-col">
            <div
                v-if="conversation"
                class="mb-4 flex items-center justify-between gap-3 border-b pb-3"
            >
                <h1 class="truncate text-lg font-semibold">
                    {{ conversation.title }}
                </h1>
                <Button
                    variant="ghost"
                    size="icon"
                    class="shrink-0 text-red-600 hover:text-red-700 dark:text-red-400"
                    aria-label="Delete conversation"
                    @click="confirmingDelete = true"
                >
                    <Trash2 />
                </Button>
            </div>

            <div
                v-if="messages.length === 0"
                class="flex flex-1 flex-col items-center justify-center gap-2 py-16 text-center"
            >
                <h1 class="text-xl font-semibold">
                    Ask your documents anything
                </h1>
                <p class="max-w-md text-sm text-muted-foreground">
                    Knowbase searches your team's documents and answers with
                    numbered sources, so you can check where every answer comes
                    from.
                </p>
            </div>

            <ol v-else class="flex flex-1 flex-col gap-6 pb-4">
                <li
                    v-for="message in messages"
                    :key="message.id"
                    class="flex"
                    :class="
                        message.role === 'user'
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <!-- Question -->
                    <div
                        v-if="message.role === 'user'"
                        class="max-w-[85%] rounded-2xl bg-primary px-4 py-2 text-sm whitespace-pre-wrap text-primary-foreground"
                    >
                        {{ message.content }}
                    </div>

                    <!-- Answer -->
                    <div v-else class="max-w-[85%] space-y-3">
                        <p
                            v-if="message.content === ''"
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <LoaderCircle class="size-4 animate-spin" />
                            Searching your documents…
                        </p>

                        <p
                            v-else
                            class="text-sm leading-relaxed whitespace-pre-wrap"
                        >
                            <template
                                v-for="(part, index) in parts(message.content)"
                                :key="index"
                            >
                                <template v-if="part.type === 'text'">{{
                                    part.text
                                }}</template>
                                <template v-else>
                                    <template
                                        v-for="number in part.numbers"
                                        :key="number"
                                    >
                                        <button
                                            v-if="sourceFor(message, number)"
                                            type="button"
                                            class="mx-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded bg-muted px-1 align-text-top text-xs font-medium hover:bg-primary hover:text-primary-foreground"
                                            :title="
                                                sourceLabel(
                                                    sourceFor(message, number)!,
                                                )
                                            "
                                            @click="
                                                toggleSource(message, number)
                                            "
                                        >
                                            {{ number }}
                                        </button>
                                        <template v-else
                                            >[{{ number }}]</template
                                        >
                                    </template>
                                </template>
                            </template>
                        </p>

                        <!-- The source passage behind a clicked citation -->
                        <div
                            v-if="openSourceOf(message)"
                            class="rounded-lg border bg-muted/40 p-3 text-sm"
                        >
                            <p class="mb-1 font-medium">
                                [{{ openSourceOf(message)!.number }}]
                                {{ sourceLabel(openSourceOf(message)!) }}
                            </p>
                            <p
                                class="max-h-48 overflow-y-auto whitespace-pre-wrap text-muted-foreground"
                            >
                                {{ openSourceOf(message)!.content }}
                            </p>
                        </div>

                        <!-- Cited sources -->
                        <div
                            v-if="citedSources(message).length"
                            class="flex flex-wrap gap-2"
                        >
                            <button
                                v-for="source in citedSources(message)"
                                :key="source.number"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                                @click="toggleSource(message, source.number)"
                            >
                                <FileText class="size-3" />
                                [{{ source.number }}] {{ sourceLabel(source) }}
                            </button>
                        </div>
                    </div>
                </li>
            </ol>

            <div ref="bottom" />

            <!-- Question box, stays at the bottom of the screen -->
            <form
                class="sticky bottom-0 space-y-2 bg-background pt-2 pb-4"
                @submit.prevent="send"
            >
                <p v-if="error" class="text-sm text-red-600 dark:text-red-400">
                    {{ error }}
                </p>

                <div
                    class="flex items-end gap-2 rounded-xl border bg-background p-2 shadow-xs focus-within:ring-2 focus-within:ring-ring"
                >
                    <textarea
                        v-model="question"
                        rows="2"
                        :maxlength="maxQuestionLength"
                        :disabled="sending"
                        placeholder="Ask a question about your documents…"
                        aria-label="Your question"
                        class="max-h-48 flex-1 resize-none bg-transparent px-2 py-1 text-sm outline-none placeholder:text-muted-foreground disabled:opacity-60"
                        @keydown.enter.exact.prevent="send"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        :disabled="sending || question.trim() === ''"
                        aria-label="Send"
                    >
                        <LoaderCircle v-if="sending" class="animate-spin" />
                        <SendHorizontal v-else />
                    </Button>
                </div>

                <p class="text-xs text-muted-foreground">
                    Enter to send, Shift+Enter for a new line.
                    <span v-if="remaining < 200"
                        >{{ remaining }} characters left.</span
                    >
                </p>
            </form>
        </section>
    </div>

    <Dialog v-model:open="confirmingDelete">
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Delete this conversation?</DialogTitle>
                <DialogDescription>
                    All its questions and answers are removed. This cannot be
                    undone.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">Cancel</Button>
                </DialogClose>
                <Button variant="destructive" @click="deleteConversation">
                    Delete conversation
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
