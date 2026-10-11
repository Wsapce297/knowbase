<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';
import TeamInvitationController from '@/actions/App/Http/Controllers/Teams/TeamInvitationController';
import TeamMemberController from '@/actions/App/Http/Controllers/Teams/TeamMemberController';
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
import type {
    RoleOption,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
    TeamSummary,
} from '@/types';

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

const props = defineProps<{
    team: TeamSummary;
    members: TeamMember[];
    invitations: TeamInvitation[];
    assignableRoles: RoleOption[];
    permissions: TeamPermissions;
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const selectClass =
    'block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function changeRole(member: TeamMember, event: Event): void {
    const role = (event.target as HTMLSelectElement).value;

    router.patch(
        TeamMemberController.update.url({
            team: props.team.id,
            user: member.id,
        }),
        { role },
        { preserveScroll: true },
    );
}

function removeMember(member: TeamMember): void {
    const leaving = member.id === currentUserId.value;
    const message = leaving
        ? `Leave "${props.team.name}"? You will lose access to its documents and chats.`
        : `Remove ${member.name} from "${props.team.name}"?`;

    if (!window.confirm(message)) {
        return;
    }

    router.delete(
        TeamMemberController.destroy.url({
            team: props.team.id,
            user: member.id,
        }),
        { preserveScroll: true },
    );
}

function cancelInvitation(invitation: TeamInvitation): void {
    router.delete(
        TeamInvitationController.destroy.url({
            team: props.team.id,
            invitation: invitation.id,
        }),
        { preserveScroll: true },
    );
}
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
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">
                        {{ member.name }}
                        <span
                            v-if="member.id === currentUserId"
                            class="text-muted-foreground"
                            >(you)</span
                        >
                    </p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ member.email }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <select
                        v-if="
                            permissions.canUpdateMemberRole &&
                            member.role !== 'owner'
                        "
                        :value="member.role"
                        :class="[selectClass, 'w-28 py-1']"
                        :aria-label="`Role for ${member.name}`"
                        @change="changeRole(member, $event)"
                    >
                        <option
                            v-for="option in assignableRoles"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <span
                        v-else
                        class="rounded-md border px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        {{ teamRoleLabels[member.role] }}
                    </span>

                    <Button
                        v-if="member.canRemove"
                        variant="ghost"
                        size="sm"
                        class="text-red-600 hover:text-red-700 dark:text-red-400"
                        @click="removeMember(member)"
                    >
                        {{ member.id === currentUserId ? 'Leave' : 'Remove' }}
                    </Button>
                </div>
            </li>
        </ul>
    </div>

    <!-- Invite -->
    <div v-if="permissions.canManageMembers" class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Invite a teammate"
            description="They'll get an email with a link to join. Links expire after 7 days."
        />

        <Form
            v-bind="TeamInvitationController.store.form(team.id)"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="space-y-4"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-4 sm:grid-cols-[1fr_9rem]">
                <div class="grid gap-2">
                    <Label for="invite-email">Email address</Label>
                    <Input
                        id="invite-email"
                        type="email"
                        name="email"
                        required
                        placeholder="colleague@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="invite-role">Role</Label>
                    <select id="invite-role" name="role" :class="selectClass">
                        <option
                            v-for="option in assignableRoles"
                            :key="option.value"
                            :value="option.value"
                            :selected="option.value === 'member'"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.role" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="invite-button">
                    Send invitation
                </Button>
                <p
                    v-show="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Invitation sent.
                </p>
            </div>
        </Form>

        <div v-if="invitations.length" class="space-y-2">
            <p class="text-sm font-medium">Pending invitations</p>
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="flex flex-wrap items-center justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ invitation.email }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ teamRoleLabels[invitation.role] }} ·
                            <span
                                v-if="invitation.expired"
                                class="text-red-600 dark:text-red-400"
                                >Expired</span
                            >
                            <span v-else
                                >Expires {{ invitation.expiresAt }}</span
                            >
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="cancelInvitation(invitation)"
                    >
                        Cancel
                    </Button>
                </li>
            </ul>
        </div>
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
