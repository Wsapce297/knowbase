export type WidgetSettings = {
    publicKey: string;
    enabled: boolean;
    allowedOrigins: string[];
    greeting: string | null;
    embedCode: string;
};

export type WidgetPermissions = {
    canManage: boolean;
};

/**
 * Data the widget page (inside the iframe) receives from the server.
 */
export type WidgetFrameConfig = {
    key: string;
    teamName: string;
    greeting: string;
};

/**
 * A cited source as a public visitor sees it: title and page, no passage text.
 */
export type WidgetSource = {
    number: number;
    documentTitle: string;
    page: number | null;
};

/**
 * One message in the widget. Questions keep a temporary negative id
 * (the server only sends back the answer).
 */
export type WidgetMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    sources: WidgetSource[];
    createdAt: string | null;
};

/**
 * Events streamed by POST widget/{key}/messages.
 * visitorToken is only set when the question started a new conversation.
 */
export type WidgetStreamEvent =
    | { type: 'text'; text: string }
    | { type: 'done'; message: WidgetMessage; visitorToken: string | null }
    | { type: 'error'; message: string };
