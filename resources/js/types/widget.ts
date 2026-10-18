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
