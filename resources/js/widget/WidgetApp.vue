<script setup lang="ts">
import { ArrowUp, FileText, MessageCircle, RotateCcw, X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import type { WidgetFrameConfig, WidgetMessage } from '@/types/widget';
import { isEmbedded, onHostMessage, postToHost } from './host';
import { loadConversation, saveConversation } from './storage';
import {
    MAX_QUESTION_LENGTH,
    streamWidgetMessage,
    WidgetRequestError,
} from './widgetStream';

const props = defineProps<{
    config: WidgetFrameConfig;
}>();

const saved = loadConversation(props.config.key);

const messages = ref<WidgetMessage[]>(saved.messages);
const visitorToken = ref<string | null>(saved.visitorToken);
const question = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const streamingId = ref<number | null>(null);

const scroller = ref<HTMLElement | null>(null);
const input = ref<HTMLTextAreaElement | null>(null);

// Questions get temporary negative ids. Continue below any saved ones so keys stay unique.
let lastTemporaryId = Math.min(0, ...saved.messages.map((m) => m.id));

function nextTemporaryId(): number {
    lastTemporaryId -= 1;

    return lastTemporaryId;
}

function persist(): void {
    saveConversation(props.config.key, {
        visitorToken: visitorToken.value,
        messages: messages.value,
    });
}

/**
 * Keep the newest text in view, unless the visitor scrolled up to read something.
 */
function scrollToBottom(force = false): void {
    const element = scroller.value;

    if (!element) {
        return;
    }

    const nearBottom =
        element.scrollHeight - element.scrollTop - element.clientHeight < 80;

    if (force || nearBottom) {
        void nextTick(() => element.scrollTo({ top: element.scrollHeight }));
    }
}

async function send(): Promise<void> {
    const text = question.value.trim();

    if (text === '' || sending.value) {
        return;
    }

    error.value = null;
    sending.value = true;
    question.value = '';

    const sentToken = visitorToken.value;

    const userMessage: WidgetMessage = {
        id: nextTemporaryId(),
        role: 'user',
        content: text,
        sources: [],
        createdAt: new Date().toISOString(),
    };

    // reactive() so that changing reply.content below updates the screen.
    const reply = reactive<WidgetMessage>({
        id: nextTemporaryId(),
        role: 'assistant',
        content: '',
        sources: [],
        createdAt: null,
    });

    messages.value.push(userMessage, reply);
    streamingId.value = reply.id;
    scrollToBottom(true);

    let failure: string | null = null;
    let finished = false;

    try {
        await streamWidgetMessage(
            props.config.key,
            { question: text, visitor_token: sentToken },
            (event) => {
                if (event.type === 'text') {
                    reply.content += event.text;
                    scrollToBottom();
                } else if (event.type === 'done') {
                    Object.assign(reply, event.message);
                    visitorToken.value =
                        event.visitorToken ?? visitorToken.value;
                    finished = true;
                } else {
                    failure = event.message;
                }
            },
        );

        if (!finished) {
            failure ??= 'The answer was interrupted. Please try again.';
        }
    } catch (e) {
        if (
            e instanceof WidgetRequestError &&
            e.status === 404 &&
            sentToken !== null
        ) {
            // The saved conversation no longer exists on the server: forget it.
            messages.value = [];
            visitorToken.value = null;
            failure =
                'That conversation has ended. Send your question again to start a new one.';
        } else {
            failure =
                e instanceof WidgetRequestError
                    ? e.message
                    : 'Could not reach the chat service. Check your connection and try again.';
        }
    }

    streamingId.value = null;
    sending.value = false;

    if (failure !== null) {
        // Nothing was saved on the server: take the question back so it can be resent.
        messages.value = messages.value.filter(
            (m) => m.id !== userMessage.id && m.id !== reply.id,
        );
        question.value = question.value || text;
        error.value = failure;
    }

    persist();

    await nextTick();
    input.value?.focus();
}

function startOver(): void {
    messages.value = [];
    visitorToken.value = null;
    error.value = null;
    persist();
    input.value?.focus();
}

/**
 * "[1]" and "[1, 3]" in answers become small superscript markers.
 */
function textParts(content: string): { text: string; citation: boolean }[] {
    return content
        .split(/(\[\d+(?:\s*,\s*\d+)*\])/)
        .filter((part) => part !== '')
        .map((part) => ({ text: part, citation: /^\[\d/.test(part) }));
}

const timeFormat = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

/** Server times are UTC; this shows them in the visitor's own time zone. */
function formatTime(iso: string): string {
    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? '' : timeFormat.format(date);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        postToHost('close');
    }
}

let stopListening: (() => void) | null = null;

onMounted(() => {
    scrollToBottom(true);

    stopListening = onHostMessage((type) => {
        if (type === 'open') {
            input.value?.focus();
            scrollToBottom(true);
        }
    });

    window.addEventListener('keydown', onKeydown);

    // Tell the loader the chat works, so it can show the button.
    postToHost('ready');
});

onBeforeUnmount(() => {
    stopListening?.();
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="flex h-dvh flex-col bg-background text-foreground">
        <header class="flex items-center gap-3 border-b px-4 py-3">
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <MessageCircle class="size-4" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                    {{ config.teamName }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Answers from our help documents
                </p>
            </div>
            <button
                v-if="messages.length > 0"
                type="button"
                class="flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                title="Start a new conversation"
                aria-label="Start a new conversation"
                :disabled="sending"
                @click="startOver"
            >
                <RotateCcw class="size-4" />
            </button>
            <button
                v-if="isEmbedded"
                type="button"
                class="flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                title="Close"
                aria-label="Close chat"
                @click="postToHost('close')"
            >
                <X class="size-4" />
            </button>
        </header>

        <main
            ref="scroller"
            class="flex-1 space-y-3 overflow-y-auto p-4"
            aria-live="polite"
        >
            <div
                class="max-w-[85%] rounded-2xl rounded-tl-sm bg-muted px-3 py-2 text-sm whitespace-pre-line"
            >
                {{ config.greeting }}
            </div>

            <div
                v-for="message in messages"
                :key="message.id"
                class="flex flex-col"
                :class="message.role === 'user' ? 'items-end' : 'items-start'"
            >
                <div
                    class="max-w-[85%] rounded-2xl px-3 py-2 text-sm break-words whitespace-pre-line"
                    :class="
                        message.role === 'user'
                            ? 'rounded-tr-sm bg-primary text-primary-foreground'
                            : 'rounded-tl-sm bg-muted'
                    "
                >
                    <span
                        v-if="
                            message.content === '' && message.id === streamingId
                        "
                        class="flex gap-1 py-1.5"
                        aria-label="Writing an answer"
                    >
                        <span
                            v-for="dot in 3"
                            :key="dot"
                            class="size-1.5 animate-bounce rounded-full bg-muted-foreground"
                            :style="{ animationDelay: `${(dot - 1) * 150}ms` }"
                        />
                    </span>
                    <template v-else>
                        <template
                            v-for="(part, index) in textParts(message.content)"
                            :key="index"
                        >
                            <sup
                                v-if="part.citation"
                                class="ml-0.5 text-[0.7em] font-medium text-muted-foreground"
                                >{{ part.text }}</sup
                            >
                            <template v-else>{{ part.text }}</template>
                        </template>
                    </template>
                </div>

                <ul
                    v-if="message.sources.length > 0"
                    class="mt-1.5 flex max-w-[85%] flex-wrap gap-1.5"
                    aria-label="Sources"
                >
                    <li
                        v-for="source in message.sources"
                        :key="source.number"
                        class="flex min-w-0 items-center gap-1 rounded-full border px-2 py-0.5 text-xs text-muted-foreground"
                    >
                        <FileText class="size-3 shrink-0" />
                        <span class="truncate">
                            [{{ source.number }}] {{ source.documentTitle
                            }}<template v-if="source.page !== null"
                                >, page {{ source.page }}</template
                            >
                        </span>
                    </li>
                </ul>

                <span
                    v-if="message.createdAt && message.id !== streamingId"
                    class="mt-1 px-1 text-[11px] text-muted-foreground"
                >
                    {{ formatTime(message.createdAt) }}
                </span>
            </div>
        </main>

        <footer class="border-t p-3">
            <p v-if="error" role="alert" class="mb-2 text-xs text-destructive">
                {{ error }}
            </p>

            <form class="flex items-end gap-2" @submit.prevent="send">
                <label for="widget-question" class="sr-only">
                    Your question
                </label>
                <!-- Enter sends, Shift+Enter adds a new line. field-sizing-content grows the box with its text. -->
                <textarea
                    id="widget-question"
                    ref="input"
                    v-model="question"
                    rows="1"
                    :maxlength="MAX_QUESTION_LENGTH"
                    placeholder="Ask a question…"
                    class="field-sizing-content max-h-32 min-h-9 flex-1 resize-none rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                    @keydown.enter.exact.prevent="send"
                />
                <button
                    type="submit"
                    class="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground disabled:opacity-50"
                    aria-label="Send"
                    :disabled="sending || question.trim() === ''"
                >
                    <ArrowUp class="size-4" />
                </button>
            </form>

            <p class="mt-2 text-center text-[11px] text-muted-foreground">
                Powered by Knowbase · AI answers can be wrong
            </p>
        </footer>
    </div>
</template>
