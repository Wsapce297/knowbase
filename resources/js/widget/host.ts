/**
 * Talking to the website that embeds the widget (the "host page") with postMessage.
 *
 * Messages always look like { source: 'knowbase-widget', type: '...' }:
 *   iframe → host: 'ready' (show the button), 'close' (hide the chat)
 *   host → iframe: 'open'  (the chat was opened: focus the question box)
 */

export type HostMessageType = 'ready' | 'close';

const SOURCE = 'knowbase-widget';

/** False when /widget/{key} is opened directly in a browser tab. */
export const isEmbedded = window.parent !== window;

export function postToHost(type: HostMessageType): void {
    if (!isEmbedded) {
        return;
    }

    // '*' because the iframe can't know which allowed website it is on.
    // That's safe: these messages carry no data, and the loader checks they come from our iframe.
    window.parent.postMessage({ source: SOURCE, type }, '*');
}

/**
 * Listen for messages from the host page. Returns a function that stops listening.
 */
export function onHostMessage(handler: (type: string) => void): () => void {
    const listener = (event: MessageEvent): void => {
        if (event.source !== window.parent) {
            return; // only the page that embeds us
        }

        const data = event.data as { source?: unknown; type?: unknown } | null;

        if (data?.source === SOURCE && typeof data.type === 'string') {
            handler(data.type);
        }
    };

    window.addEventListener('message', listener);

    return () => window.removeEventListener('message', listener);
}
