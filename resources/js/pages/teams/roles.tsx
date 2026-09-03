import {
    Add01Icon,
    Delete02Icon,
    Edit03Icon,
    MoreHorizontalIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Field,
    FieldContent,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
    FieldTitle,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
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
import { destroy, store, update } from '@/routes/teams/roles';
import type { Team, WorkspacePermission, WorkspaceRole } from '@/types';

type Props = {
    team: Team;
    roles: WorkspaceRole[];
    permissions: WorkspacePermission[];
};

export default function TeamRoles({ team, roles, permissions }: Props) {
    const [roleToEdit, setRoleToEdit] = useState<WorkspaceRole | null>(null);
    const [roleToDelete, setRoleToDelete] = useState<WorkspaceRole | null>(
        null,
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const deleteRole = () => {
        if (!roleToDelete) {
            return;
        }

        router.delete(destroy.url([team.slug, roleToDelete.id]), {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onSuccess: () => setRoleToDelete(null),
            onError: () =>
                toast.add({
                    type: 'error',
                    title: 'This role could not be deleted.',
                }),
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title={`Roles · ${team.name}`} />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="Roles" />

                <SettingsPanel
                    variant="inset"
                    title="Roles"
                    description="Manage workspace roles and choose the permissions each role can use."
                    actions={
                        <Button
                            data-test="create-workspace-role"
                            onClick={() => setCreateOpen(true)}
                        >
                            <HugeiconsIcon
                                icon={Add01Icon}
                                data-icon="inline-start"
                            />
                            Create role
                        </Button>
                    }
                >
                    <div className="p-3 sm:p-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="h-12 px-5">
                                        Role
                                    </TableHead>
                                    <TableHead className="h-12 px-5">
                                        Permissions
                                    </TableHead>
                                    <TableHead className="h-12 w-[1%] px-5 text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {roles.map((role) => (
                                    <TableRow key={role.id} className="h-16">
                                        <TableCell className="px-5 py-4">
                                            <div className="flex items-center gap-2 font-medium">
                                                <span>{role.label}</span>
                                                {role.is_system ? (
                                                    <Badge variant="secondary">
                                                        System
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        </TableCell>
                                        <TableCell className="px-5 py-4 text-muted-foreground">
                                            {role.permissions.length} permission
                                            {role.permissions.length === 1
                                                ? ''
                                                : 's'}
                                        </TableCell>
                                        <TableCell className="w-[1%] px-5 py-4 text-right">
                                            {!role.is_owner ? (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        render={
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                data-test="workspace-role-actions"
                                                                aria-label={`Actions for ${role.label}`}
                                                            />
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={
                                                                MoreHorizontalIcon
                                                            }
                                                        />
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuGroup>
                                                            <DropdownMenuItem
                                                                data-test="edit-workspace-role"
                                                                onClick={() =>
                                                                    setRoleToEdit(
                                                                        role,
                                                                    )
                                                                }
                                                            >
                                                                <HugeiconsIcon
                                                                    icon={
                                                                        Edit03Icon
                                                                    }
                                                                />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            {!role.is_system ? (
                                                                <>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        variant="destructive"
                                                                        data-test="delete-workspace-role"
                                                                        onClick={() =>
                                                                            setRoleToDelete(
                                                                                role,
                                                                            )
                                                                        }
                                                                    >
                                                                        <HugeiconsIcon
                                                                            icon={
                                                                                Delete02Icon
                                                                            }
                                                                        />
                                                                        Delete
                                                                    </DropdownMenuItem>
                                                                </>
                                                            ) : null}
                                                        </DropdownMenuGroup>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            ) : null}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </SettingsPanel>
            </div>

            <RoleDialog
                key={String(createOpen)}
                team={team}
                permissions={permissions}
                open={createOpen}
                onOpenChange={setCreateOpen}
            />

            {roleToEdit ? (
                <RoleDialog
                    key={roleToEdit.id}
                    team={team}
                    permissions={permissions}
                    role={roleToEdit}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setRoleToEdit(null);
                        }
                    }}
                />
            ) : null}

            <Dialog
                open={roleToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRoleToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete role</DialogTitle>
                        <DialogDescription>
                            Delete <strong>{roleToDelete?.label}</strong>? This
                            role must not be assigned to members or pending
                            invitations.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <DialogClose render={<Button variant="secondary" />}>
                            Cancel
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={deleting}
                            onClick={deleteRole}
                        >
                            Delete role
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

type RoleDialogProps = {
    team: Team;
    permissions: WorkspacePermission[];
    role?: WorkspaceRole;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function RoleDialog({
    team,
    permissions,
    role,
    open,
    onOpenChange,
}: RoleDialogProps) {
    const isEditing = role !== undefined;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...(isEditing
                        ? update.form.patch([team.slug, role.id])
                        : store.form(team.slug))}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {isEditing ? 'Edit role' : 'Create role'}
                                </DialogTitle>
                                <DialogDescription>
                                    {isEditing
                                        ? 'Update this role name and the permissions it grants.'
                                        : 'Roles are available only within this workspace.'}
                                </DialogDescription>
                            </DialogHeader>

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="role-name">
                                        Role name
                                    </FieldLabel>
                                    <Input
                                        id="role-name"
                                        name="name"
                                        placeholder="Campaign manager"
                                        autoFocus
                                        required
                                        maxLength={50}
                                        defaultValue={role?.label ?? ''}
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>

                                <FieldSet>
                                    <FieldLegend>Permissions</FieldLegend>
                                    <FieldGroup className="gap-3">
                                        {permissions.map((permission) => (
                                            <Field
                                                key={permission.value}
                                                orientation="horizontal"
                                            >
                                                <Checkbox
                                                    id={`permission-${permission.value}`}
                                                    name="permissions[]"
                                                    value={permission.value}
                                                    defaultChecked={role?.permissions.includes(
                                                        permission.value,
                                                    )}
                                                />
                                                <FieldContent>
                                                    <FieldTitle>
                                                        {permission.label}
                                                    </FieldTitle>
                                                </FieldContent>
                                            </Field>
                                        ))}
                                    </FieldGroup>
                                    <FieldError>
                                        {errors.permissions}
                                    </FieldError>
                                </FieldSet>
                            </FieldGroup>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={<Button variant="secondary" />}
                                >
                                    Cancel
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : null}
                                    {isEditing ? 'Save changes' : 'Create role'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
