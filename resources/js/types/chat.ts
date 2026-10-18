export type ChatSource = {
    number: number;
    documentId: number;
    documentTitle: string;
    page: number | null;
    content: string;
    similarity: number;
    cited: boolean;
};

export type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    sources: ChatSource[];
    createdAt: string;
};

export type ConversationSummary = {
    id: number;
    title: string;
    updatedAt: string;
};

export type ConversationInfo = {
    id: number;
    title: string;
};

/** Events sent by ChatMessageController while an answer streams. */
export type ChatStreamEvent =
    | { type: 'text'; text: string }
    | { type: 'done'; conversationId: number; message: ChatMessage }
    | { type: 'error'; message: string };
