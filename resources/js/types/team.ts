export type TeamRole = 'owner' | 'admin' | 'member';

export type TeamSummary = {
    id: number;
    name: string;
    personal: boolean;
    role: TeamRole | null;
};

export type SharedTeams = {
    current: TeamSummary | null;
    all: TeamSummary[];
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: TeamRole;
};

export type TeamPermissions = {
    canUpdate: boolean;
    canManageMembers: boolean;
    canDelete: boolean;
};

export const teamRoleLabels: Record<TeamRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
};
