<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref, useTemplateRef, watch } from 'vue';
import WidgetSettingsController from '@/actions/App/Http/Controllers/Widgets/WidgetSettingsController';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TeamSummary } from '@/types';
import type { WidgetPermissions, WidgetSettings } from '@/types/widget';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Website widget',
                href: WidgetSettingsController.edit(),
            },
        ],
    },
});

const props = defineProps<{
    team: TeamSummary;
    widget: WidgetSettings | null;
    maxOrigins: number;
    maxGreetingLength: number;
    permissions: WidgetPermissions;
}>();

const textareaClass =
    'block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

// The checkbox is mirrored into a hidden input, so "off" is sent as 0
// (an unticked checkbox would send nothing at all).
const enabled = ref(props.widget?.enabled ?? false);

watch(
    () => props.widget?.enabled,
    (value) => {
        enabled.value = value ?? false;
    },
);

// Copying the embed code.
const embedField = useTemplateRef<HTMLTextAreaElement>('embedField');
const copied = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | undefined;

async function copyEmbedCode(): Promise<void> {
    const field = embedField.value;

    if (!field) {
        return;
    }

    try {
        // navigator.clipboard only exists on https or localhost.
        // http://knowbase.test is neither, so fall back to the older copy command.
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(field.value);
        } else {
            field.select();
            document.execCommand('copy');
        }
    } catch {
        field.select();

        return;
    }

    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copied.value = false), 2000);
}

function selectAll(event: Event): void {
    (event.target as HTMLTextAreaElement).select();
}

// Regenerating the key.
const regenerateOpen = ref(false);
const regenerating = ref(false);

function regenerateKey(): void {
    router.post(
        WidgetSettingsController.regenerateKey.url(props.team.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (regenerating.value = true),
            onFinish: () => (regenerating.value = false),
            onSuccess: () => (regenerateOpen.value = false),
        },
    );
}
</script>

<template>
    <Head title="Website widget" />

    <h1 class="sr-only">Website widget</h1>

    <!-- Settings -->
    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Website widget"
            description="A chat box for your own website. Visitors ask questions and get answers from this team's documents, without signing in."
        />

        <Form
            v-if="permissions.canManage"
            :key="team.id"
            v-bind="WidgetSettingsController.update.form(team.id)"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="flex items-start gap-3">
                <input
                    type="hidden"
                    name="enabled"
                    :value="enabled ? '1' : '0'"
                />
                <input
                    id="enabled"
                    v-model="enabled"
                    type="checkbox"
                    class="mt-0.5 size-4 shrink-0 accent-primary"
                />
                <div class="grid gap-1">
                    <Label for="enabled">Show the widget on my websites</Label>
                    <p class="text-sm text-muted-foreground">
                        While this is off, the widget stays hidden everywhere.
                    </p>
                    <InputError :message="errors.enabled" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="allowed_origins">Allowed websites</Label>
                <textarea
                    id="allowed_origins"
                    name="allowed_origins"
                    rows="4"
                    spellcheck="false"
                    autocomplete="off"
                    :class="textareaClass"
                    :value="(widget?.allowedOrigins ?? []).join('\n')"
                    :placeholder="'https://example.com\nhttps://help.example.com'"
                />
                <p class="text-sm text-muted-foreground">
                    One address per line, up to {{ maxOrigins }}. The widget
                    only appears on these websites. Use https:// (http:// works
                    for local test sites like localhost).
                </p>
                <InputError :message="errors.allowed_origins" />
            </div>

            <div class="grid gap-2">
                <Label for="greeting">Greeting</Label>
                <Input
                    id="greeting"
                    name="greeting"
                    :default-value="widget?.greeting ?? ''"
                    :maxlength="maxGreetingLength"
                    placeholder="Hi! Ask me anything about our product."
                />
                <p class="text-sm text-muted-foreground">
                    The first message visitors see. Leave empty for a standard
                    greeting.
                </p>
                <InputError :message="errors.greeting" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="save-widget-button">
                    {{ widget ? 'Save' : 'Create widget' }}
                </Button>
                <p
                    v-show="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </p>
            </div>
        </Form>

        <div v-else class="space-y-1 rounded-lg border p-4 text-sm">
            <p v-if="widget">
                The widget is
                <span class="font-medium">{{
                    widget.enabled ? 'on' : 'off'
                }}</span
                ><template v-if="widget.allowedOrigins.length">
                    and allowed on
                    {{ widget.allowedOrigins.join(', ') }}</template
                >.
            </p>
            <p v-else>This team doesn't have a website widget yet.</p>
            <p class="text-muted-foreground">
                Only owners and admins can change the website widget.
            </p>
        </div>
    </div>

    <!-- Embed code -->
    <div v-if="widget" class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Embed code"
            description="Paste this into your website, just before the closing body tag, on every page where the chat box should appear."
        />

        <div class="space-y-3">
            <textarea
                ref="embedField"
                readonly
                rows="3"
                aria-label="Embed code"
                :value="widget.embedCode"
                :class="[textareaClass, 'resize-none font-mono text-xs']"
                @focus="selectAll"
            />

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    @click="copyEmbedCode"
                >
                    <Check v-if="copied" />
                    <Copy v-else />
                    {{ copied ? 'Copied' : 'Copy code' }}
                </Button>
                <p
                    v-if="!widget.enabled"
                    class="text-sm text-amber-600 dark:text-amber-400"
                >
                    The widget is switched off, so this code shows nothing yet.
                </p>
            </div>
        </div>
    </div>

    <!-- Key -->
    <div v-if="widget" class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Widget key"
            description="Identifies this team in the embed code. It isn't secret: anyone can see it in your website's code."
        />

        <div class="space-y-4">
            <code
                class="block rounded-md border bg-muted px-3 py-2 font-mono text-xs break-all"
                >{{ widget.publicKey }}</code
            >

            <Dialog v-if="permissions.canManage" v-model:open="regenerateOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" data-test="regenerate-key-button">
                        Regenerate key
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Regenerate the widget key?</DialogTitle>
                        <DialogDescription>
                            The current embed code stops working straight away.
                            You'll need to paste the new code into every website
                            that uses the widget.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="regenerating"
                            @click="regenerateKey"
                        >
                            Regenerate key
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
