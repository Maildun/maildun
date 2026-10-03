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
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    campaignSeries?: string | null;
};

const EDITOR_LABELS: Record<EmailEditorMode, string> = {
    html: 'HTML',
    builder: 'Email Builder',
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
    campaignSeries = null,
}: Props) {
    const form = useForm<{
        name: string;
        template: string | null;
        campaign_series: string | null;
    }>({
        name: '',
        template: initialTemplate,
        campaign_series: campaignSeries,
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

    const selectedTemplate = templates.find(
        (template) => template.uuid === form.data.template,
    );
    const templateItems = [
        { value: 'empty', label: emptyLabel },
        ...templates.map((template) => ({
            value: template.uuid,
            label: template.name,
        })),
    ];

    const renderOption = (template: EmailTemplateSummary) => (
        <SelectItem
            key={template.uuid}
            value={template.uuid}
            data-test="template-option"
        >
            <span className="flex w-full items-center justify-between gap-2">
                <span>{template.name}</span>
                <Badge variant="secondary">
                    {EDITOR_LABELS[template.editor]}
                </Badge>
            </span>
        </SelectItem>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    !templateIsLocked && 'max-h-[85vh] overflow-y-auto',
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
                                <FieldLabel htmlFor="email-template">
                                    Start from
                                </FieldLabel>
                                <Select
                                    items={templateItems}
                                    value={form.data.template ?? 'empty'}
                                    onValueChange={(value) => {
                                        if (value !== null) {
                                            form.setData(
                                                'template',
                                                value === 'empty'
                                                    ? null
                                                    : value,
                                            );
                                        }
                                    }}
                                >
                                    <SelectTrigger
                                        id="email-template"
                                        data-test="template-select"
                                        className="w-full"
                                        aria-invalid={Boolean(
                                            form.errors.template,
                                        )}
                                        aria-describedby="email-template-description email-template-error"
                                    >
                                        <SelectValue placeholder="Select a template" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem
                                                value="empty"
                                                data-test="template-option"
                                            >
                                                <span className="flex w-full items-center justify-between gap-2">
                                                    <span>{emptyLabel}</span>
                                                    <Badge variant="secondary">
                                                        {
                                                            EDITOR_LABELS[
                                                                defaultEditor
                                                            ]
                                                        }
                                                    </Badge>
                                                </span>
                                            </SelectItem>
                                        </SelectGroup>
                                        {starters.length > 0 && (
                                            <SelectGroup>
                                                <SelectLabel>
                                                    Starter templates
                                                </SelectLabel>
                                                {starters.map(renderOption)}
                                            </SelectGroup>
                                        )}
                                        {saved.length > 0 && (
                                            <SelectGroup>
                                                <SelectLabel>
                                                    Your templates
                                                </SelectLabel>
                                                {saved.map(renderOption)}
                                            </SelectGroup>
                                        )}
                                    </SelectContent>
                                </Select>
                                <FieldDescription id="email-template-description">
                                    {form.data.template === null
                                        ? "Uses the team's default editor."
                                        : selectedTemplate?.description}
                                </FieldDescription>
                                <FieldError id="email-template-error">
                                    {form.errors.template}
                                </FieldError>
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
