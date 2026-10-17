export type DocumentStatus = 'pending' | 'processing' | 'ready' | 'failed';

export type DocumentItem = {
    id: number;
    title: string;
    originalName: string;
    size: number;
    status: DocumentStatus;
    statusLabel: string;
    finished: boolean;
    error: string | null;
    chunksCount: number;
    uploadedBy: string | null;
    uploadedAt: string;
    canDelete: boolean;
};

export type DocumentPermissions = {
    canUpload: boolean;
};
