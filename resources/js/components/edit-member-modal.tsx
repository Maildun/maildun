import { router } from '@inertiajs/react';
import { useState } from 'react';
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
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { toast } from '@/components/ui/toast';
import { update as updateMember } from '@/routes/teams/members';
import type { RoleOption, Team, TeamMember } from '@/types';

type Props = {
    team: Team;
    member: TeamMember;
    availableRoles: RoleOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function EditMemberModal({
    team,
    member,
    availableRoles,
    open,
    onOpenChange,
}: Props) {
    const [role, setRole] = useState<RoleOption['value']>(
        member.role as RoleOption['value'],
    );
    const [processing, setProcessing] = useState(false);

    const updateRole = () => {
        if (role === member.role) {
            onOpenChange(false);

            return;
        }

        router.visit(updateMember([team.slug, member.id]), {
            data: { role },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => onOpenChange(false),
            onError: () =>
                toast.add({
                    type: 'error',
                    title: "Failed to update the member's role.",
                }),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit workspace member</DialogTitle>
                    <DialogDescription>
                        Update the role for <strong>{member.name}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="member-role">Role</FieldLabel>
                        <Select
                            value={role}
                            onValueChange={(value) =>
                                setRole(value as RoleOption['value'])
                            }
                        >
                            <SelectTrigger
                                id="member-role"
                                data-test="edit-member-role"
                                className="w-full"
                            >
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {availableRoles.map((roleOption) => (
                                        <SelectItem
                                            key={roleOption.value}
                                            value={roleOption.value}
                                        >
                                            {roleOption.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                </FieldGroup>

                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>
                    <Button
                        data-test="edit-member-submit"
                        disabled={processing || role === member.role}
                        onClick={updateRole}
                    >
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
