import {
    Delete02Icon,
    Edit03Icon,
    Key01Icon,
    Mail01Icon,
    MoreHorizontalIcon,
    ResetPasswordIcon,
    UserAdd01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    generatePassword,
    sendPasswordResetLink,
} from '@/actions/App/Http/Controllers/Teams/TeamMemberController';
import CancelInvitationModal from '@/components/cancel-invitation-modal';
import EditMemberModal from '@/components/edit-member-modal';
import InviteMemberModal from '@/components/invite-member-modal';
import RemoveMemberModal from '@/components/remove-member-modal';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
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
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldLabel } from '@/components/ui/field';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/toast';
import { useInitials } from '@/hooks/use-initials';
import { formatRelativeTime } from '@/lib/format';
import { index as membersIndex } from '@/routes/teams/members';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';
import type { Paginated } from '@/types/audiences';

type Props = {
    team: Team;
    members: Paginated<TeamMember>;
    invitations: Paginated<TeamInvitation>;
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
};

type GeneratedMemberPassword = {
    memberId: number;
    memberName: string;
    password: string;
};

export default function TeamMembers({
    team,
    members,
    invitations,
    permissions,
    availableRoles,
}: Props) {
    const getInitials = useInitials();

    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [removeMemberDialogOpen, setRemoveMemberDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<TeamMember | null>(
        null,
    );
    const [memberToEdit, setMemberToEdit] = useState<TeamMember | null>(null);
    const [editMemberDialogOpen, setEditMemberDialogOpen] = useState(false);
    const [memberToResetPassword, setMemberToResetPassword] =
        useState<TeamMember | null>(null);
    const [memberToGeneratePassword, setMemberToGeneratePassword] =
        useState<TeamMember | null>(null);
    const [generatedMemberPassword, setGeneratedMemberPassword] =
        useState<GeneratedMemberPassword | null>(null);
    const [isGeneratingPassword, setIsGeneratingPassword] = useState(false);
    const [cancelInvitationDialogOpen, setCancelInvitationDialogOpen] =
        useState(false);
    const [invitationToCancel, setInvitationToCancel] =
        useState<TeamInvitation | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const memberPassword = (event as CustomEvent).detail?.flash
                ?.memberPassword as GeneratedMemberPassword | undefined;

            if (memberPassword?.password) {
                setMemberToGeneratePassword(null);
                setGeneratedMemberPassword(memberPassword);
            }
        });
    }, []);

    const confirmRemoveMember = (member: TeamMember) => {
        setMemberToRemove(member);
        setRemoveMemberDialogOpen(true);
    };

    const editMember = (member: TeamMember) => {
        setMemberToEdit(member);
        setEditMemberDialogOpen(true);
    };

    const generateMemberPassword = () => {
        if (!memberToGeneratePassword) {
            return;
        }

        router.post(
            generatePassword.url([team.slug, memberToGeneratePassword.id]),
            {},
            {
                preserveScroll: true,
                onStart: () => setIsGeneratingPassword(true),
                onFinish: () => setIsGeneratingPassword(false),
            },
        );
    };

    const sendMemberPasswordResetLink = () => {
        if (!memberToResetPassword) {
            return;
        }

        const member = memberToResetPassword;

        setMemberToResetPassword(null);

        router.post(
            sendPasswordResetLink.url([team.slug, member.id]),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const confirmGenerateMemberPassword = () => {
        if (!memberToResetPassword) {
            return;
        }

        setMemberToGeneratePassword(memberToResetPassword);
        setMemberToResetPassword(null);
    };

    const confirmCancelInvitation = (invitation: TeamInvitation) => {
        setInvitationToCancel(invitation);
        setCancelInvitationDialogOpen(true);
    };

    return (
        <>
            <Head title={`Members · ${team.name}`} />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="Members" />
                <SettingsPanel
                    variant="inset"
                    title="Workspace members"
                    description={
                        permissions.canCreateInvitation
                            ? `${members.total} ${members.total === 1 ? 'person has' : 'people have'} access to this workspace.`
                            : `${members.total} ${members.total === 1 ? 'person has' : 'people have'} workspace access.`
                    }
                    actions={
                        permissions.canCreateInvitation ? (
                            <Button
                                data-test="invite-member-button"
                                onClick={() => setInviteDialogOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={UserAdd01Icon}
                                    data-icon="inline-start"
                                />
                                Invite member
                            </Button>
                        ) : undefined
                    }
                >
                    <div className="p-3 sm:p-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="h-12 px-5">
                                        Member
                                    </TableHead>
                                    <TableHead className="h-12 px-5">
                                        Role
                                    </TableHead>
                                    {permissions.canUpdateMember ||
                                    permissions.canRemoveMember ? (
                                        <TableHead className="h-12 w-[1%] px-5 text-right">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.data.map((member) => (
                                    <TableRow
                                        key={member.id}
                                        data-test="member-row"
                                        className="h-20"
                                    >
                                        <TableCell className="max-w-0 px-5 py-4">
                                            <div className="flex min-w-64 items-center gap-3">
                                                <Avatar className="size-10">
                                                    {member.avatar ? (
                                                        <AvatarImage
                                                            src={member.avatar}
                                                            alt={member.name}
                                                        />
                                                    ) : null}
                                                    <AvatarFallback>
                                                        {getInitials(
                                                            member.name,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">
                                                        {member.name}
                                                    </p>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {member.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="w-36 px-5 py-4">
                                            <Badge
                                                variant="secondary"
                                                data-test="member-role-badge"
                                            >
                                                {member.role_label}
                                            </Badge>
                                        </TableCell>
                                        {permissions.canUpdateMember ||
                                        permissions.canRemoveMember ? (
                                            <TableCell className="w-[1%] px-5 py-4 text-right">
                                                {member.role !== 'owner' ? (
                                                    <MemberActions
                                                        member={member}
                                                        canEdit={
                                                            permissions.canUpdateMember
                                                        }
                                                        canDelete={
                                                            permissions.canRemoveMember
                                                        }
                                                        onEdit={editMember}
                                                        onResetPassword={
                                                            setMemberToResetPassword
                                                        }
                                                        onDelete={
                                                            confirmRemoveMember
                                                        }
                                                    />
                                                ) : null}
                                            </TableCell>
                                        ) : null}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <ListPagination
                            id="members"
                            team={team}
                            pageName="page"
                            perPageName="members_per_page"
                            paginator={members}
                        />
                    </div>
                </SettingsPanel>

                {invitations.total > 0 ? (
                    <SettingsPanel
                        variant="inset"
                        title="Pending invitations"
                        description={`${invitations.total} ${invitations.total === 1 ? 'invitation is' : 'invitations are'} awaiting a response.`}
                    >
                        <div className="p-3 sm:p-4">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="h-12 px-5">
                                            Email
                                        </TableHead>
                                        <TableHead className="h-12 px-5">
                                            Role
                                        </TableHead>
                                        <TableHead className="hidden h-12 px-5 sm:table-cell">
                                            Sent
                                        </TableHead>
                                        {permissions.canCancelInvitation ? (
                                            <TableHead className="h-12 w-[1%] px-5 text-right">
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </TableHead>
                                        ) : null}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invitations.data.map((invitation) => (
                                        <TableRow
                                            key={invitation.code}
                                            data-test="invitation-row"
                                            className="h-16"
                                        >
                                            <TableCell className="max-w-0 px-5 py-4">
                                                <p className="min-w-56 truncate font-medium">
                                                    {invitation.email}
                                                </p>
                                            </TableCell>
                                            <TableCell className="w-36 px-5 py-4">
                                                <Badge variant="secondary">
                                                    {invitation.role_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="hidden px-5 py-4 text-muted-foreground sm:table-cell">
                                                <time
                                                    dateTime={
                                                        invitation.created_at
                                                    }
                                                    title={new Date(
                                                        invitation.created_at,
                                                    ).toLocaleString()}
                                                >
                                                    {formatRelativeTime(
                                                        invitation.created_at,
                                                    )}
                                                </time>
                                            </TableCell>
                                            {permissions.canCancelInvitation ? (
                                                <TableCell className="w-[1%] px-5 py-4 text-right">
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        data-test="invitation-cancel-button"
                                                        onClick={() =>
                                                            confirmCancelInvitation(
                                                                invitation,
                                                            )
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                </TableCell>
                                            ) : null}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <ListPagination
                                id="invitations"
                                team={team}
                                pageName="invitations_page"
                                perPageName="invitations_per_page"
                                paginator={invitations}
                            />
                        </div>
                    </SettingsPanel>
                ) : null}
            </div>

            {permissions.canCreateInvitation ? (
                <InviteMemberModal
                    team={team}
                    availableRoles={availableRoles}
                    open={inviteDialogOpen}
                    onOpenChange={setInviteDialogOpen}
                />
            ) : null}

            <RemoveMemberModal
                team={team}
                member={memberToRemove}
                open={removeMemberDialogOpen}
                onOpenChange={setRemoveMemberDialogOpen}
            />

            {memberToEdit ? (
                <EditMemberModal
                    key={memberToEdit.id}
                    team={team}
                    member={memberToEdit}
                    availableRoles={availableRoles}
                    open={editMemberDialogOpen}
                    onOpenChange={(open) => {
                        setEditMemberDialogOpen(open);

                        if (!open) {
                            setMemberToEdit(null);
                        }
                    }}
                />
            ) : null}

            <Dialog
                open={memberToResetPassword !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setMemberToResetPassword(null);
                    }
                }}
            >
                <DialogContent className="w-fit">
                    <DialogHeader>
                        <DialogTitle>Reset password</DialogTitle>
                        <DialogDescription>
                            Choose how {memberToResetPassword?.name} should
                            regain access to this workspace.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose render={<Button variant="secondary" />}>
                            Cancel
                        </DialogClose>
                        <ButtonGroup aria-label="Password reset actions">
                            <Button
                                type="button"
                                variant="outline"
                                data-test="member-send-reset-email-button"
                                onClick={sendMemberPasswordResetLink}
                            >
                                <HugeiconsIcon
                                    icon={Mail01Icon}
                                    data-icon="inline-start"
                                />
                                Send reset email
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Generate new password"
                                title="Generate new password"
                                data-test="member-generate-password-button"
                                onClick={confirmGenerateMemberPassword}
                            >
                                <HugeiconsIcon icon={ResetPasswordIcon} />
                            </Button>
                        </ButtonGroup>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={memberToGeneratePassword !== null}
                onOpenChange={(open) => {
                    if (!open && !isGeneratingPassword) {
                        setMemberToGeneratePassword(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Generate a new password?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This immediately replaces{' '}
                            {memberToGeneratePassword?.name}'s current password.
                            Copy the new password and share it securely.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isGeneratingPassword}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            type="button"
                            disabled={isGeneratingPassword}
                            onClick={generateMemberPassword}
                        >
                            {isGeneratingPassword ? (
                                <Spinner data-icon="inline-start" />
                            ) : null}
                            Generate password
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Dialog
                open={generatedMemberPassword !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setGeneratedMemberPassword(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Copy {generatedMemberPassword?.memberName}'s new
                            password
                        </DialogTitle>
                        <DialogDescription>
                            Store this password securely before closing this
                            dialog.
                        </DialogDescription>
                    </DialogHeader>

                    <Alert>
                        <HugeiconsIcon icon={Key01Icon} />
                        <AlertTitle>
                            This password is shown only once
                        </AlertTitle>
                        <AlertDescription>
                            It cannot be viewed again after you close this
                            dialog. Never send it by email or chat.
                        </AlertDescription>
                    </Alert>

                    <code className="block rounded-md bg-muted px-3 py-3 text-xs break-all">
                        {generatedMemberPassword?.password}
                    </code>

                    <DialogFooter>
                        <DialogClose render={<Button variant="secondary" />}>
                            I've saved it
                        </DialogClose>
                        <Button
                            type="button"
                            data-test="member-copy-generated-password-button"
                            onClick={async () => {
                                if (!generatedMemberPassword) {
                                    return;
                                }

                                await navigator.clipboard.writeText(
                                    generatedMemberPassword.password,
                                );
                                toast.add({
                                    type: 'success',
                                    title: 'Password copied.',
                                });
                            }}
                        >
                            Copy
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <CancelInvitationModal
                team={team}
                invitation={invitationToCancel}
                open={cancelInvitationDialogOpen}
                onOpenChange={setCancelInvitationDialogOpen}
            />
        </>
    );
}

function MemberActions({
    member,
    canEdit,
    canDelete,
    onEdit,
    onResetPassword,
    onDelete,
}: {
    member: TeamMember;
    canEdit: boolean;
    canDelete: boolean;
    onEdit: (member: TeamMember) => void;
    onResetPassword: (member: TeamMember) => void;
    onDelete: (member: TeamMember) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        size="icon"
                        variant="ghost"
                        data-test="member-actions"
                        aria-label={`Actions for ${member.name}`}
                    />
                }
            >
                <HugeiconsIcon icon={MoreHorizontalIcon} />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-max">
                {canEdit ? (
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            data-test="edit-member-button"
                            onClick={() => onEdit(member)}
                        >
                            <HugeiconsIcon icon={Edit03Icon} />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            data-test="member-reset-password-button"
                            onClick={() => onResetPassword(member)}
                        >
                            <HugeiconsIcon icon={Mail01Icon} />
                            Reset password
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                ) : null}
                {canEdit && canDelete ? <DropdownMenuSeparator /> : null}
                {canDelete ? (
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            variant="destructive"
                            data-test="delete-member-button"
                            onClick={() => onDelete(member)}
                        >
                            <HugeiconsIcon icon={Delete02Icon} />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ListPagination<T>({
    id,
    team,
    pageName,
    perPageName,
    paginator,
}: {
    id: string;
    team: Team;
    pageName: string;
    perPageName: string;
    paginator: Paginated<T>;
}) {
    const { url } = usePage();
    const rowsPerPageId = `${id}-rows-per-page`;

    const changeRowsPerPage = (value: string | null) => {
        if (!value) {
            return;
        }

        const query = new URLSearchParams(url.split('?')[1]);

        query.set(perPageName, value);
        query.set(pageName, '1');

        router.get(
            membersIndex.url(team.slug),
            Object.fromEntries(query.entries()),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <footer className="border-t px-5 py-4">
            <div className="flex items-center justify-between gap-4">
                <Field orientation="horizontal" className="w-fit">
                    <FieldLabel htmlFor={rowsPerPageId}>
                        Rows per page
                    </FieldLabel>
                    <Select
                        value={String(paginator.per_page)}
                        onValueChange={changeRowsPerPage}
                    >
                        <SelectTrigger className="w-20" id={rowsPerPageId}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent align="start">
                            <SelectGroup>
                                {[10, 25, 50, 100].map((perPage) => (
                                    <SelectItem
                                        key={perPage}
                                        value={String(perPage)}
                                    >
                                        {perPage}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </Field>
                <Pagination className="mx-0 w-auto">
                    <PaginationContent>
                        <PaginationItem>
                            <PaginationPrevious
                                {...paginationLinkProps(
                                    paginator.prev_page_url,
                                )}
                            />
                        </PaginationItem>
                        <PaginationItem>
                            <PaginationNext
                                {...paginationLinkProps(
                                    paginator.next_page_url,
                                )}
                            />
                        </PaginationItem>
                    </PaginationContent>
                </Pagination>
            </div>
        </footer>
    );
}

function paginationLinkProps(url: string | null) {
    return {
        disabled: !url,
        render: url ? <Link href={url} preserveScroll /> : undefined,
    };
}
