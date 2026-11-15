/**
 * Read a "server-sent events" response and call onEvent for every event.
 *
 * The server answers with blocks of text like
 *   data: {"type":"text","text":"Hello"}
 * separated by a blank line. They can arrive split across network
 * chunks, so we collect text in a buffer and only handle complete blocks.
 *
 * Used by both the chat page (chatStream.ts) and the website widget (widget/widgetStream.ts).
 */
export async function readServerSentEvents<T>(
    response: Response,
    onEvent: (event: T) => void,
): Promise<void> {
    if (!response.body) {
        return;
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
                onEvent(JSON.parse(block.slice(6)) as T);
            }

            end = buffer.indexOf('\n\n');
        }
    }
}
