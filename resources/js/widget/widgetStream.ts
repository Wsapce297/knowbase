import WidgetMessageController from '@/actions/App/Http/Controllers/Widgets/WidgetMessageController';
import { readServerSentEvents } from '@/lib/eventStream';
import type { WidgetStreamEvent } from '@/types/widget';

/** Same as WidgetMessageRequest::MAX_LENGTH on the server. */
export const MAX_QUESTION_LENGTH = 1000;

/**
 * Thrown when the question never reached the answering stage
 * (validation, rate limit, unknown widget or conversation).
 * The message is visitor friendly; status lets the caller react to a 404.
 */
export class WidgetRequestError extends Error {
    readonly status: number;

    constructor(message: string, status: number) {
        super(message);
        this.name = 'WidgetRequestError';
        this.status = status;
    }
}

async function errorFor(response: Response): Promise<string> {
    const data = (await response.json().catch(() => ({}))) as {
        message?: string;
    };

    switch (response.status) {
        case 422:
            return data.message ?? 'Please check your question.';
        case 429:
            return (
                data.message ??
                'Too many questions. Please wait a minute and try again.'
            );
        case 403:
            return 'This chat only works on the website it was added to.';
        case 404:
            return 'This chat is not available right now.';
        default:
            return 'Something went wrong. Please try again.';
    }
}

/**
 * Send a visitor's question and call onEvent for every streamed event.
 * visitor_token is null for the first question of a conversation.
 */
export async function streamWidgetMessage(
    key: string,
    body: { question: string; visitor_token: string | null },
    onEvent: (event: WidgetStreamEvent) => void,
): Promise<void> {
    const response = await fetch(WidgetMessageController.store.url({ key }), {
        method: 'POST',
        credentials: 'omit', // the visitor is anonymous: no cookies needed or wanted
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json, text/event-stream',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok || !response.body) {
        throw new WidgetRequestError(await errorFor(response), response.status);
    }

    await readServerSentEvents<WidgetStreamEvent>(response, onEvent);
}
