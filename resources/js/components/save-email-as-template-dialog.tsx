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
import type { EmailBuilderDocument } from '@/types';

type Props = {
    teamSlug: string;
    emailType: 'campaign' | 'transactional';
    emailUuid: string;
    defaultName: string;
    subject: string;
    preheader: string;
    html: string;
    source: string;
    design: EmailBuilderDocument | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Freezes the email's current body into a reusable template. The body travels
 * with the form rather than being read back off the email, so what gets saved
 * is exactly what the author is looking at. The editor is not sent: the server
 * reads it off the email named by emailType and emailUuid.
 */
export default function SaveEmailAsTemplateDialog({
    teamSlug,
    emailType,
    emailUuid,
    defaultName,
    subject,
    preheader,
    html,
    source,
    design,
    open,
    onOpenChange,
}: Props) {
    const form = useForm<{
        name: string;
        description: string;
        subject: string;
        preheader: string;
        html: string;
        source: string;
        design: EmailBuilderDocument | null;
    }>({
        name: defaultName,
        description: '',
        subject,
        preheader,
        html,
        source,
        design,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            email_type: emailType,
            email_uuid: emailUuid,
            subject,
            preheader,
            html,
            source,
            design,
        }));
        form.post(store.url(teamSlug), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Save as template</DialogTitle>
                        <DialogDescription>
                            Adds this campaign&apos;s current content to your
                            team&apos;s template library.
                        </DialogDescription>
                    </DialogHeader>

                    <FieldGroup className="mt-4">
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="template-name">
                                Name
                            </FieldLabel>
                            <Input
                                id="template-name"
                                data-test="save-template-name"
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
                            <FieldLabel htmlFor="template-description">
                                Description
                            </FieldLabel>
                            <Input
                                id="template-description"
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
                            data-test="save-template-submit"
                            disabled={form.processing}
                        >
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Save template
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
