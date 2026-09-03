import { Form } from '@inertiajs/react';
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
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/automations';

type Props = {
    teamSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function CreateAutomationDialog({
    teamSlug,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    action={store(teamSlug)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>New automation</DialogTitle>
                                <DialogDescription>
                                    Name it now — you will pick the trigger and
                                    steps on the canvas next.
                                </DialogDescription>
                            </DialogHeader>

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="automation-name">
                                        Name
                                    </FieldLabel>
                                    <Input
                                        id="automation-name"
                                        name="name"
                                        autoFocus
                                        required
                                        placeholder="Welcome series"
                                        data-test="automation-name-input"
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(errors.description)}
                                >
                                    <FieldLabel htmlFor="automation-description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="automation-description"
                                        name="description"
                                        rows={3}
                                        placeholder="Greets everyone who joins the newsletter."
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.description}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={<Button variant="secondary" />}
                                >
                                    Cancel
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="create-automation-submit"
                                >
                                    Create automation
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
