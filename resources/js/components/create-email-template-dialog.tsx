import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/email_templates';

type Props = {
    teamSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function CreateEmailTemplateDialog({
    teamSlug,
    open,
    onOpenChange,
}: Props) {
    const form = useForm<{
        name: string;
        description: string;
        compose: boolean;
    }>({
        name: '',
        description: '',
        compose: true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(store.url(teamSlug), {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>New template</DialogTitle>
                        <DialogDescription>
                            Name it, then compose the email your team will start
                            campaigns from.
                        </DialogDescription>
                    </DialogHeader>

                    <FieldGroup className="mt-4">
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="create-template-name">
                                Name
                            </FieldLabel>
                            <Input
                                id="create-template-name"
                                data-test="email-template-name"
                                autoFocus
                                required
                                maxLength={255}
                                placeholder="Weekly digest"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.description)}>
                            <FieldLabel htmlFor="create-template-description">
                                Description
                            </FieldLabel>
                            <Input
                                id="create-template-description"
                                maxLength={255}
                                placeholder="Our usual layout"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.description)}
                            />
                            <FieldDescription>
                                Optional, shown in the compose picker.
                            </FieldDescription>
                            <FieldError>{form.errors.description}</FieldError>
                        </Field>
                    </FieldGroup>

                    <DialogFooter className="mt-6 gap-2">
                        <DialogClose
                            render={
                                <Button type="button" variant="secondary" />
                            }
                        >
                            Cancel
                        </DialogClose>
                        <Button
                            type="submit"
                            data-test="email-template-submit"
                            disabled={form.processing}
                        >
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Start composing
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
