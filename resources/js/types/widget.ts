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
