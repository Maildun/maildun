import { AlertCircleIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/audiences';
import type { AudienceSummary } from '@/types/audiences';

type DeletableAudience = Pick<
    AudienceSummary,
    | 'uuid'
    | 'name'
    | 'avatar'
    | 'subscribers_count'
    | 'segments_count'
    | 'forms_count'
>;

type DeleteAudienceDialogProps = {
    teamSlug: string;
    audience: DeletableAudience | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DeleteAudienceDialog({
    teamSlug,
    audience,
    open,
    onOpenChange,
}: DeleteAudienceDialogProps) {
    if (!audience) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="w-lg gap-0 overflow-hidden p-0"
                showCloseButton={false}
            >
                <DialogHeader className="gap-4 px-6 pt-6 pb-5">
                    <div className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive dark:bg-destructive/20">
                        <HugeiconsIcon
                            icon={AlertCircleIcon}
                            className="size-5"
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <DialogTitle>Delete this audience?</DialogTitle>
                        <DialogDescription>
                            This action permanently removes the audience and
                            everything it contains. It cannot be undone.
                        </DialogDescription>
                    </div>
                </DialogHeader>

                <div className="flex items-center gap-3 border-y bg-muted/40 px-6 py-4 dark:bg-muted/20">
                    <Avatar className="size-9 rounded-md after:rounded-md">
                        <AvatarImage
                            src={audience.avatar}
                            alt=""
                            className="rounded-md"
                        />
                        <AvatarFallback className="rounded-md">
                            {audience.name.charAt(0).toUpperCase()}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {audience.name}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {audience.subscribers_count} subscribers,{' '}
                            {audience.segments_count} segments, and{' '}
                            {audience.forms_count} forms will be deleted.
                        </p>
                    </div>
                </div>

                <Form
                    {...destroy.form([teamSlug, audience.uuid])}
                    className="flex flex-col"
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="px-6 py-6">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="delete-audience-name">
                                        To confirm, type{' '}
                                        <span className="font-semibold text-foreground">
                                            {audience.name}
                                        </span>{' '}
                                        below
                                    </FieldLabel>
                                    <Input
                                        id="delete-audience-name"
                                        name="name"
                                        placeholder="Type the audience name"
                                        autoComplete="off"
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                            </div>
                            <DialogFooter className="border-t bg-muted/30 px-6 py-4 dark:bg-muted/15">
                                <DialogClose
                                    render={<Button variant="outline" />}
                                >
                                    Cancel
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Delete audience
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
