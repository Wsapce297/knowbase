<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import type { WidgetFrameConfig } from '@/types/widget';

defineProps<{
    config: WidgetFrameConfig;
}>();
</script>

<template>
    <div class="flex h-dvh flex-col bg-background text-foreground">
        <header class="flex items-center gap-3 border-b px-4 py-3">
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <MessageCircle class="size-4" />
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">
                    {{ config.teamName }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Answers from our help documents
                </p>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4">
            <div
                class="max-w-[85%] rounded-2xl rounded-tl-sm bg-muted px-3 py-2 text-sm"
            >
                {{ config.greeting }}
            </div>
        </main>
    </div>
</template>
