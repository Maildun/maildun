import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { store } from '@/routes/emails';
import type { EmailEditorMode, EmailTemplateSummary } from '@/types';

type Props = {
    teamSlug: string;
    templates: EmailTemplateSummary[];
    defaultEditor: EmailEditorMode;
    /** Preselect a template — used when composing straight from the gallery. */
    initialTemplate?: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    storeUrl?: string;
    title?: string;
    description?: string;
    emptyLabel?: string;
    submitLabel?: string;
    namePlaceholder?: string;
    nameTestId?: string;
    submitTestId?: string;
};

const EDITOR_LABELS: Record<EmailEditorMode, string> = {
    html: 'HTML',
    builder: 'EmailBuilder.js',
    plain_text: 'Plain text',
    markdown: 'Markdown',
};

export default function EmailTemplatePicker({
    teamSlug,
    templates,
    defaultEditor,
    initialTemplate = null,
    open,
    onOpenChange,
    storeUrl,
    title = 'Compose campaign',
    description = 'Name the draft and pick what to start from.',
    emptyLabel = 'Empty campaign',
    submitLabel = 'Start composing',
    namePlaceholder = 'March newsletter',
    nameTestId = 'email-name-input',
    submitTestId = 'create-email-submit',
}: Props) {
    const form = useForm<{ name: string; template: string | null }>({
        name: '',
        template: initialTemplate,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeUrl ?? store.url(teamSlug), {
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    const lockedTemplate = initialTemplate
        ? (templates.find((template) => template.uuid === initialTemplate) ??
          null)
        : null;
    const templateIsLocked = initialTemplate !== null;
    const starters = templates.filter((template) => template.is_starter);
    const saved = templates.filter((template) => !template.is_starter);

    const renderOption = (template: EmailTemplateSummary) => (
        <button
            key={template.uuid}
            type="button"
            data-test="template-option"
            onClick={() => form.setData('template', template.uuid)}
            className={cn(
                'flex flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors hover:bg-accent',
                form.data.template === template.uuid &&
                    'border-primary ring-1 ring-primary',
            )}
        >
            <span className="flex w-full items-center justify-between gap-2">
                <span className="text-sm font-medium">{template.name}</span>
                <Badge variant="secondary">
                    {EDITOR_LABELS[template.editor]}
                </Badge>
            </span>
            {template.description ? (
                <span className="text-xs text-muted-foreground">
                    {template.description}
                </span>
            ) : null}
        </button>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    !templateIsLocked && 'max-h-[85vh] w-2xl overflow-y-auto',
                )}
            >
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            {templateIsLocked
                                ? `Name the campaign to start from ${lockedTemplate?.name ?? 'this template'}.`
                                : description}
                        </DialogDescription>
                    </DialogHeader>

                    <FieldGroup className="mt-4">
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="email-name">Name</FieldLabel>
                            <Input
                                id="email-name"
                                data-test={nameTestId}
                                autoFocus
                                required
                                maxLength={255}
                                placeholder={namePlaceholder}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>

                        {!templateIsLocked && (
                            <Field data-invalid={Boolean(form.errors.template)}>
                                <FieldLabel>Start from</FieldLabel>

                                <button
                                    type="button"
                                    data-test="template-option"
                                    onClick={() =>
                                        form.setData('template', null)
                                    }
                                    className={cn(
                                        'flex flex-col items-start gap-1 rounded-lg border p-3 text-left transition-colors hover:bg-accent',
                                        form.data.template === null &&
                                            'border-primary ring-1 ring-primary',
                                    )}
                                >
                                    <span className="flex w-full items-center justify-between gap-2">
                                        <span className="text-sm font-medium">
                                            {emptyLabel}
                                        </span>
                                        <Badge variant="secondary">
                                            {EDITOR_LABELS[defaultEditor]}
                                        </Badge>
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Uses the team&apos;s default editor.
                                    </span>
                                </button>

                                {starters.length > 0 && (
                                    <>
                                        <p className="mt-2 text-xs font-medium text-muted-foreground">
                                            Starter templates
                                        </p>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {starters.map(renderOption)}
                                        </div>
                                    </>
                                )}

                                {saved.length > 0 && (
                                    <>
                                        <p className="mt-2 text-xs font-medium text-muted-foreground">
                                            Your templates
                                        </p>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {saved.map(renderOption)}
                                        </div>
                                    </>
                                )}

                                <FieldError>{form.errors.template}</FieldError>
                            </Field>
                        )}
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
                            data-test={submitTestId}
                            disabled={form.processing}
                        >
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
