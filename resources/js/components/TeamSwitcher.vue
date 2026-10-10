<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import CurrentTeamController from '@/actions/App/Http/Controllers/Teams/CurrentTeamController';
import TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { teamRoleLabels } from '@/types';
import type { TeamSummary } from '@/types';

const page = usePage();

const teams = computed(() => page.props.teams);
const current = computed(() => teams.value?.current ?? null);

const createOpen = ref(false);

function switchTo(team: TeamSummary): void {
    if (team.id === current.value?.id) {
        return;
    }

    router.put(CurrentTeamController.update.url(), { team_id: team.id });
}

function initial(name: string): string {
    return name.trim().charAt(0).toUpperCase() || '?';
}

function subtitle(team: TeamSummary): string {
    if (team.personal) {
        return 'Personal team';
    }

    return team.role ? teamRoleLabels[team.role] : '';
}
</script>

<template>
    <SidebarMenu v-if="teams && current">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="team-switcher"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sm font-semibold text-sidebar-primary-foreground"
                        >
                            {{ initial(current.name) }}
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-medium">{{
                                current.name
                            }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ subtitle(current) }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    align="start"
                    side="bottom"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Teams
                    </DropdownMenuLabel>

                    <DropdownMenuItem
                        v-for="team in teams.all"
                        :key="team.id"
                        class="gap-2 p-2"
                        @select="switchTo(team)"
                    >
                        <div
                            class="flex size-6 items-center justify-center rounded-sm border text-xs font-semibold"
                        >
                            {{ initial(team.name) }}
                        </div>
                        <span class="flex-1 truncate">{{ team.name }}</span>
                        <Check
                            v-if="team.id === current.id"
                            class="size-4 text-muted-foreground"
                        />
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem
                        class="gap-2 p-2"
                        data-test="create-team"
                        @select="createOpen = true"
                    >
                        <div
                            class="flex size-6 items-center justify-center rounded-md border bg-transparent"
                        >
                            <Plus class="size-4" />
                        </div>
                        <span class="font-medium text-muted-foreground">
                            Create team
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog v-model:open="createOpen">
                <DialogContent>
                    <Form
                        v-bind="TeamController.store.form()"
                        class="space-y-6"
                        reset-on-success
                        @success="createOpen = false"
                        v-slot="{ errors, processing }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Create a new team</DialogTitle>
                            <DialogDescription>
                                Teams share documents and chats. You can invite
                                people after creating it.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="team-name">Team name</Label>
                            <Input
                                id="team-name"
                                name="name"
                                required
                                placeholder="e.g. Acme Support"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button type="submit" :disabled="processing">
                                Create team
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
