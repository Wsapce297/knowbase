import type { WidgetMessage } from '@/types/widget';

/**
 * Remembers the visitor's conversation in the iframe's localStorage,
 * so it survives page reloads and moving between pages of the website.
 *
 * Browsers keep an iframe's storage separate per website ("storage partitioning"),
 * so a visitor's chat on acme.com is never visible on another site using Knowbase.
 * Storage can be blocked or full, so every call is wrapped in try/catch:
 * the widget then still works, it just forgets the chat on reload.
 */

/** Bump when the stored shape changes; older data is then ignored. */
const VERSION = 1;

const MAX_STORED_MESSAGES = 40;

export type StoredConversation = {
    visitorToken: string | null;
    messages: WidgetMessage[];
};

function storageKey(widgetKey: string): string {
    return `knowbase-widget:${widgetKey}`;
}

export function loadConversation(widgetKey: string): StoredConversation {
    try {
        const raw = window.localStorage.getItem(storageKey(widgetKey));

        if (raw !== null) {
            const data = JSON.parse(raw) as {
                version?: unknown;
                visitorToken?: unknown;
                messages?: unknown;
            };

            if (data.version === VERSION && Array.isArray(data.messages)) {
                return {
                    visitorToken:
                        typeof data.visitorToken === 'string'
                            ? data.visitorToken
                            : null,
                    messages: data.messages as WidgetMessage[],
                };
            }
        }
    } catch {
        // Blocked or corrupted storage: start fresh.
    }

    return { visitorToken: null, messages: [] };
}

export function saveConversation(
    widgetKey: string,
    conversation: StoredConversation,
): void {
    try {
        if (
            conversation.visitorToken === null &&
            conversation.messages.length === 0
        ) {
            window.localStorage.removeItem(storageKey(widgetKey));

            return;
        }

        window.localStorage.setItem(
            storageKey(widgetKey),
            JSON.stringify({
                version: VERSION,
                visitorToken: conversation.visitorToken,
                messages: conversation.messages.slice(-MAX_STORED_MESSAGES),
            }),
        );
    } catch {
        // Blocked or full storage: the chat still works for this page view.
    }
}
