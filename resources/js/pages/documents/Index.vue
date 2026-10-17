<script setup lang="ts">
import { Form, Head, router, usePoll } from '@inertiajs/vue3';
import { FileText, LoaderCircle, Trash2 } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    DocumentItem,
    DocumentPermissions,
    DocumentStatus,
} from '@/types/document';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Documents',
                href: DocumentController.index(),
            },
        ],
    },
});

const props = defineProps<{
    documents: DocumentItem[];
    permissions: DocumentPermissions;
    maxUploadMb: number;
}>();

/*
 * Live status: while any document is still waiting or processing,
 * reload just the "documents" prop every 3 seconds. Stop when all are finished.
 */
const hasUnfinished = computed(() =>
    props.documents.some((document) => !document.finished),
);

const { start, stop } = usePoll(
    3000,
    { only: ['documents'] },
    { autoStart: false },
);

onMounted(() => {
    if (hasUnfinished.value) {
        start();
    }
});

watch(hasUnfinished, (unfinished) => (unfinished ? start() : stop()));

/*
 * Delete confirmation dialog.
 */
const documentToDelete = ref<DocumentItem | null>(null);
const deleting = ref(false);

function deleteDocument(): void {
    if (!documentToDelete.value) {
        return;
    }

    router.delete(DocumentController.destroy.url(documentToDelete.value.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            documentToDelete.value = null;
        },
    });
}

function closeDeleteDialog(open: boolean): void {
    if (!open) {
        documentToDelete.value = null;
    }
}

/*
 * Display helpers.
 */
function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const statusClasses: Record<DocumentStatus, string> = {
    pending: 'border-border text-muted-foreground',
    processing:
        'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
    ready: 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-300',
    failed: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
};

const fileInputClass =
    'block w-full cursor-pointer rounded-md border border-input bg-transparent text-sm shadow-xs file:mr-3 file:border-0 file:border-r file:border-input file:bg-muted file:px-3 file:py-2 file:text-sm file:font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head title="Documents" />

    <div class="flex h-full flex-1 flex-col gap-10 p-4">
        <h1 class="sr-only">Documents</h1>

        <!-- Upload -->
        <div v-if="permissions.canUpload" class="flex max-w-3xl flex-col space-y-6">
            <Heading
                variant="small"
                title="Upload a document"
                description="Knowbase reads it, splits it into passages and makes it searchable for your team's chats."
            />

            <Form
                v-bind="DocumentController.store.form()"
                reset-on-success
                :options="{ preserveScroll: true }"
                class="space-y-4"
                v-slot="{ errors, processing, progress, recentlySuccessful }"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="file">File</Label>
                        <input
                            id="file"
                            type="file"
                            name="file"
                            accept=".pdf,.md,.txt"
                            required
                            :class="fileInputClass"
                        />
                        <p class="text-xs text-muted-foreground">
                            PDF, Markdown or text, up to {{ maxUploadMb }} MB.
                        </p>
                        <InputError :message="errors.file" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="title">
                            Title
                            <span class="font-normal text-muted-foreground">(optional)</span>
                        </Label>
                        <Input
                            id="title"
                            name="title"
                            placeholder="Defaults to the file name"
                        />
                        <InputError :message="errors.title" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" data-test="upload-button">
                        <LoaderCircle v-if="processing" class="animate-spin" />
                        Upload
                    </Button>
                    <p
                        v-if="processing && progress?.percentage"
                        class="text-sm text-muted-foreground"
                    >
                        Uploading… {{ progress.percentage }}%
                    </p>
                    <p
                        v-show="recentlySuccessful"
                        class="text-sm text-muted-foreground"
                    >
                        Uploaded. Processing has started.
                    </p>
                </div>
            </Form>
        </div>

        <!-- List -->
        <div class="flex max-w-3xl flex-col space-y-6">
            <Heading
                variant="small"
                title="Documents"
                description="Everything your team can chat with."
            />

            <div
                v-if="documents.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No documents yet. Upload your first one above.
            </div>

            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="document in documents"
                    :key="document.id"
                    class="flex flex-wrap items-start justify-between gap-3 p-4"
                >
                    <div class="flex min-w-0 flex-1 gap-3">
                        <FileText
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ document.title }}
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ document.originalName }} ·
                                {{ formatSize(document.size) }} · uploaded
                                {{ document.uploadedAt
                                }}<template v-if="document.uploadedBy">
                                    by {{ document.uploadedBy }}</template
                                >
                            </p>
                            <p
                                v-if="document.status === 'ready'"
                                class="text-sm text-muted-foreground"
                            >
                                {{ document.chunksCount }}
                                {{
                                    document.chunksCount === 1
                                        ? 'passage'
                                        : 'passages'
                                }}
                                indexed
                            </p>
                            <p
                                v-if="document.error"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ document.error }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium"
                            :class="statusClasses[document.status]"
                        >
                            <LoaderCircle
                                v-if="!document.finished"
                                class="size-3 animate-spin"
                            />
                            {{ document.statusLabel }}
                        </span>

                        <Button
                            v-if="document.canDelete"
                            variant="ghost"
                            size="icon"
                            class="text-red-600 hover:text-red-700 dark:text-red-400"
                            :aria-label="`Delete ${document.title}`"
                            @click="documentToDelete = document"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <Dialog :open="documentToDelete !== null" @update:open="closeDeleteDialog">
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>
                    Delete "{{ documentToDelete?.title }}"?
                </DialogTitle>
                <DialogDescription>
                    The file and all its indexed passages are removed. Chats
                    can no longer cite it. This cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button type="button" variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="deleting"
                    data-test="confirm-delete-document-button"
                    @click="deleteDocument"
                >
                    Delete document
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
