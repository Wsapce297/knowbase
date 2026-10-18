import ChatMessageController from '@/actions/App/Http/Controllers/Chat/ChatMessageController';
import type { ChatStreamEvent } from '@/types/chat';

/**
 * Thrown when the question never reached the answering stage
 * (validation, permissions, too many requests...). The message is user friendly.
 */
export class ChatRequestError extends Error {}

/**
 * Laravel puts an encrypted CSRF token in the XSRF-TOKEN cookie.
 * Sending it back in a header proves the request comes from our own page.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1] ?? '') : '';
}

async function errorFor(response: Response): Promise<string> {
    switch (response.status) {
        case 422: {
            const data = (await response.json().catch(() => ({}))) as {
                errors?: Record<string, string[]>;
            };

            return data.errors?.question?.[0] ?? 'Please check your question.';
        }
        case 429:
            return 'You are asking very quickly. Please wait a minute and try again.';
        case 419:
            return 'Your session has expired. Refresh the page and try again.';
        case 401:
            return 'Please log in again.';
        case 403:
        case 404:
            return 'This conversation is not available.';
        default:
            return 'Something went wrong. Please try again.';
    }
}

/**
 * Send a question and call onEvent for every streamed event.
 *
 * The server answers with "server-sent events": blocks of text like
 *   data: {"type":"text","text":"Hello"}
 * separated by a blank line. They can arrive split across network
 * chunks, so we collect text in a buffer and only handle complete blocks.
 */
export async function streamChat(
    body: { question: string; conversation_id: number | null },
    onEvent: (event: ChatStreamEvent) => void,
): Promise<void> {
    const response = await fetch(ChatMessageController.store.url(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json, text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok || !response.body) {
        throw new ChatRequestError(await errorFor(response));
    }

    const reader = response.body
        .pipeThrough(new TextDecoderStream())
        .getReader();

    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();

        if (done) {
            break;
        }

        buffer += value;

        let end = buffer.indexOf('\n\n');

        while (end !== -1) {
            const block = buffer.slice(0, end);
            buffer = buffer.slice(end + 2);

            if (block.startsWith('data: ')) {
                onEvent(JSON.parse(block.slice(6)) as ChatStreamEvent);
            }

            end = buffer.indexOf('\n\n');
        }
    }
}
