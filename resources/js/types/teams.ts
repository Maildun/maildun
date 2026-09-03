export type TeamRole = string;

export type TeamBrandColor =
    | 'blue'
    | 'indigo'
    | 'violet'
    | 'purple'
    | 'fuchsia'
    | 'pink'
    | 'rose'
    | 'red'
    | 'orange'
    | 'amber'
    | 'lime'
    | 'emerald'
    | 'teal'
    | 'cyan'
    | 'neutral';
export type TeamBrandFont =
    | 'inter'
    | 'instrument-sans'
    | 'system-sans'
    | 'rounded-sans'
    | 'humanist-sans'
    | 'serif'
    | 'georgia'
    | 'mono';
export type TeamBrandInputStyle = 'default' | 'soft' | 'underline';
export type TeamBrandTheme = {
    color: TeamBrandColor;
    font: TeamBrandFont;
    inputStyle: TeamBrandInputStyle;
};

export type Team = {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    logo: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
    brandTheme: TeamBrandTheme;
};

export type TeamMember = {
    id: number;
    uuid: string;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    teamName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    team: {
        name: string;
        slug: string;
    };
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
    canManageTags: boolean;
    canManageEmails: boolean;
    canLeaveTeam: boolean;
};

export type TeamTag = {
    uuid: string;
    name: string;
    color: string | null;
    subscribers_count: number;
    created_at: string | null;
};

export type RoleOption = {
    value: string;
    label: string;
};

export type WorkspaceRole = {
    id: number;
    name: string;
    label: string;
    is_system: boolean;
    is_owner: boolean;
    permissions: string[];
};

export type WorkspacePermission = {
    value: string;
    label: string;
};
