<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';
import TeamSettingsController from '@/actions/App/Http/Controllers/Teams/TeamSettingsController';
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
import { teamRoleLabels } from '@/types';
import type { TeamMember, TeamPermissions, TeamSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Team settings',
                href: TeamSettingsController.edit(),
            },
        ],
    },
});

defineProps<{
    team: TeamSummary;
    members: TeamMember[];
    permissions: TeamPermissions;
}>();
</script>

<template>
    <Head title="Team settings" />

    <h1 class="sr-only">Team settings</h1>

    <!-- Team name -->
    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Team"
            :description="
                team.personal
                    ? 'Your personal team. Only you can see what is in it.'
                    : 'The name your members see in the team switcher.'
            "
        />

        <Form
            v-if="permissions.canUpdate"
            :key="team.id"
            v-bind="TeamController.update.form(team.id)"
            class="space-y-6"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-2">
                <Label for="name">Team name</Label>
                <Input
                    id="name"
                    name="name"
                    class="mt-1 block w-full"
                    :default-value="team.name"
                    required
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-team-button">
                    Save
                </Button>
                <p
                    v-show="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </p>
            </div>
        </Form>

        <p v-else class="text-sm">
            <span class="font-medium">{{ team.name }}</span>
            <span class="text-muted-foreground">
                · Only owners and admins can rename this team.</span
            >
        </p>
    </div>

    <!-- Members -->
    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Members"
            description="Everyone who can access this team's documents and chats."
        />

        <ul class="divide-y rounded-lg border">
            <li
                v-for="member in members"
                :key="member.id"
                class="flex items-center justify-between gap-4 p-4"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">
                        {{ member.name }}
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ member.email }}
                    </p>
                </div>
                <span
                    class="shrink-0 rounded-md border px-2 py-0.5 text-xs font-medium text-muted-foreground"
                >
                    {{ teamRoleLabels[member.role] }}
                </span>
            </li>
        </ul>
    </div>

    <!-- Delete team -->
    <div v-if="permissions.canDelete" class="space-y-6">
        <Heading
            variant="small"
            title="Delete team"
            description="Permanently delete this team and everything in it."
        />

        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Warning</p>
                <p class="text-sm">This cannot be undone.</p>
            </div>

            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        data-test="delete-team-button"
                    >
                        Delete team
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="TeamController.destroy.form(team.id)"
                        reset-on-success
                        :options="{ preserveScroll: true }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>
                                Delete "{{ team.name }}"?
                            </DialogTitle>
                            <DialogDescription>
                                All members lose access immediately. Enter your
                                password to confirm.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Password</Label
                            >
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                            >
                                Delete team
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
