import {
    ArrowLeft01Icon,
    Delete02Icon,
    Edit03Icon,
    Mail01Icon,
    MoreHorizontalIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { CampaignSetupRow } from '@/components/campaign-setup-row';
import DeleteEmailTemplateModal from '@/components/delete-email-template-modal';
import { EmailBuilderEditor } from '@/components/email-builder-editor';
import { EmailHtmlEditor } from '@/components/email-html-editor';
import { EmailSourceEditor } from '@/components/email-source-editor';
import EmailTemplatePicker from '@/components/email-template-picker';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import {
    EMPTY_BUILDER_DOCUMENT,
    getChildrenIds,
    htmlToBuilderDocument,
    isSourceEditor,
    renderBuilderHtml,
    renderSourceHtml,
    toBuilderDocument,
} from '@/lib/email-builder';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/email_templates';
import type {
    EmailBuilderDocument,
    EmailEditorMode,
    EmailTemplateDetail,
    EmailTemplateSummary,
} from '@/types';

type Props = {
    template: EmailTemplateDetail;
    templates: EmailTemplateSummary[];
    defaultEditor: EmailEditorMode;
    canManage: boolean;
};

type SectionValue = 'details' | 'subject' | 'design';

type DialogSection = Exclude<SectionValue, 'design'>;

const SECTIONS: { value: SectionValue; fields: string[] }[] = [
    { value: 'details', fields: ['name', 'description'] },
    { value: 'subject', fields: ['subject', 'preheader'] },
    { value: 'design', fields: ['html', 'source', 'design'] },
];

const EDITOR_LABELS: Record<EmailEditorMode, string> = {
    html: 'HTML',
    builder: 'Email Builder',
    plain_text: 'Plain text',
    markdown: 'Markdown',
};

function TemplateDesignPreview({
    editor,
    design,
    html,
    source,
}: {
    editor: EmailEditorMode;
    design: EmailBuilderDocument | null;
    html: string;
    source: string;
}) {
    const previewHtml =
        editor === 'builder' && design
            ? renderBuilderHtml(toBuilderDocument(design))
            : isSourceEditor(editor) && source.trim() !== ''
              ? renderSourceHtml(source, editor)
              : html;

    if (previewHtml.trim() === '') {
        return null;
    }

    return (
        <div className="px-6 pb-6" data-test="template-design-preview">
            <div className="flex h-72 items-start justify-center overflow-hidden rounded-lg bg-muted">
                <div className="pointer-events-none h-[800px] w-[600px] shrink-0 origin-top scale-[0.48]">
                    <iframe
                        title="Template design preview"
                        sandbox=""
                        srcDoc={previewHtml}
                        tabIndex={-1}
                        className="pointer-events-none h-full w-full border-0 bg-background"
                    />
                </div>
            </div>
        </div>
    );
}

export default function EmailTemplatesEdit({
    template,
    templates,
    defaultEditor,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [view, setView] = useState<'hub' | 'design'>('hub');
    const [openSection, setOpenSection] = useState<DialogSection | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [useOpen, setUseOpen] = useState(false);

    const form = useForm<{
        name: string;
        description: string;
        subject: string;
        preheader: string;
        html: string;
        source: string;
        design: EmailBuilderDocument | null;
    }>({
        name: template.name,
        description: template.description ?? '',
        subject: template.subject ?? '',
        preheader: template.preheader ?? '',
        html: template.html ?? '',
        source: template.source ?? '',
        design:
            template.editor === 'builder'
                ? template.design
                    ? toBuilderDocument(template.design)
                    : htmlToBuilderDocument(template.html ?? '')
                : null,
    });

    useUnsavedChanges(form.isDirty);

    const designing = view === 'design';

    setLayoutProps({ fullscreen: designing });

    if (!currentTeam) {
        return null;
    }

    const errorFor = (value: SectionValue) =>
        SECTIONS.find((entry) => entry.value === value)?.fields.some(
            (field) => form.errors[field as keyof typeof form.errors],
        ) ?? false;

    const revealSection = (section: SectionValue) => {
        if (section === 'design') {
            setOpenSection(null);
            setView('design');

            return;
        }

        setView('hub');
        setOpenSection(section);
    };

    const saveTemplate = (onSuccess?: () => void) => {
        form.transform((data) => ({
            ...data,
            html:
                template.editor === 'builder' && data.design
                    ? renderBuilderHtml(data.design)
                    : isSourceEditor(template.editor)
                      ? renderSourceHtml(data.source, template.editor)
                      : data.html,
            source: isSourceEditor(template.editor) ? data.source : '',
            design: template.editor === 'builder' ? data.design : null,
        }));

        form.patch(update.url([currentTeam.slug, template.uuid]), {
            preserveScroll: true,
            onSuccess: () => {
                form.setDefaults();
                onSuccess?.();
            },
            onError: (errors) => {
                const firstSection = SECTIONS.find((entry) =>
                    entry.fields.some((field) => errors[field]),
                );

                if (firstSection) {
                    revealSection(firstSection.value);
                }
            },
        });
    };

    const save = (event: FormEvent) => {
        event.preventDefault();
        saveTemplate();
    };

    const saveSection = () => {
        saveTemplate(() => setOpenSection(null));
    };

    /**
     * Closing a section dialog without saving puts that section's fields back
     * to their saved values, so abandoned edits never linger in the form.
     */
    const changeSection = (section: DialogSection, open: boolean) => {
        if (open) {
            setOpenSection(section);

            return;
        }

        const fields = (SECTIONS.find((entry) => entry.value === section)
            ?.fields ?? []) as (keyof typeof form.data)[];

        form.reset(...fields);
        form.clearErrors(...fields);
        setOpenSection(null);
    };

    const hasBody =
        template.editor === 'builder'
            ? form.data.design !== null &&
              getChildrenIds(form.data.design).length > 0
            : isSourceEditor(template.editor)
              ? form.data.source.trim() !== ''
              : form.data.html.trim() !== '';
    const subjectDone = form.data.subject.trim() !== '';
    const descriptionDone = form.data.description.trim() !== '';
    const canUse = canManage && template.editor === defaultEditor;
    const templateName = form.data.name || template.name;

    return (
        <>
            <Head title={`Edit ${templateName}`} />

            <form
                onSubmit={save}
                className={cn(
                    designing
                        ? 'relative flex h-dvh min-h-0 w-full flex-col overflow-hidden bg-background'
                        : 'flex min-w-0 flex-col gap-6',
                )}
                data-test={
                    designing ? 'template-design-shell' : 'template-setup-hub'
                }
            >
                {designing ? (
                    <>
                        <header
                            className="flex h-14 shrink-0 items-center justify-between gap-3 border-b bg-background px-3 sm:px-4"
                            data-test="template-design-navbar"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Back to template setup"
                                    data-test="template-design-back"
                                    onClick={() => setView('hub')}
                                >
                                    <HugeiconsIcon icon={ArrowLeft01Icon} />
                                </Button>
                                <Separator
                                    orientation="vertical"
                                    className="hidden h-5 sm:block"
                                />
                                {canManage ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="group max-w-36 min-w-0 justify-start px-2 sm:max-w-56 lg:max-w-80"
                                        data-test="template-design-rename"
                                        onClick={() =>
                                            setOpenSection('details')
                                        }
                                    >
                                        <span className="truncate">
                                            {templateName}
                                        </span>
                                        <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground transition-colors group-hover:bg-accent group-hover:text-foreground">
                                            <HugeiconsIcon
                                                icon={Edit03Icon}
                                                className="size-3.5"
                                                data-icon="inline-end"
                                            />
                                        </span>
                                    </Button>
                                ) : (
                                    <p className="max-w-36 truncate px-2 text-sm font-medium sm:max-w-56 lg:max-w-80">
                                        {templateName}
                                    </p>
                                )}
                                <Badge
                                    variant="secondary"
                                    className="hidden md:inline-flex"
                                >
                                    {EDITOR_LABELS[template.editor]}
                                </Badge>
                            </div>
                            {canManage ? (
                                <Button
                                    type="submit"
                                    size="sm"
                                    data-test="save-template-button"
                                    disabled={form.processing}
                                >
                                    {form.processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Save
                                </Button>
                            ) : null}
                        </header>
                        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                            {template.editor === 'builder' ? (
                                <>
                                    <EmailBuilderEditor
                                        document={
                                            form.data.design ??
                                            EMPTY_BUILDER_DOCUMENT
                                        }
                                        disabled={!canManage}
                                        fill
                                        onChange={(design) =>
                                            form.setData('design', design)
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.design}
                                    </FieldError>
                                </>
                            ) : template.editor === 'html' ? (
                                <>
                                    <EmailHtmlEditor
                                        value={form.data.html}
                                        disabled={!canManage}
                                        aria-invalid={Boolean(form.errors.html)}
                                        onChange={(html) =>
                                            form.setData('html', html)
                                        }
                                    />
                                    <FieldError>{form.errors.html}</FieldError>
                                </>
                            ) : (
                                <>
                                    <EmailSourceEditor
                                        editor={template.editor}
                                        value={form.data.source}
                                        disabled={!canManage}
                                        aria-invalid={Boolean(
                                            form.errors.source,
                                        )}
                                        onChange={(source) =>
                                            form.setData('source', source)
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.source}
                                    </FieldError>
                                </>
                            )}
                        </div>
                    </>
                ) : (
                    <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                            <div className="flex min-w-0 flex-1 items-center gap-2">
                                <Link
                                    href={index(currentTeam.slug)}
                                    aria-label="Back to templates"
                                    className={cn(
                                        buttonVariants({
                                            variant: 'ghost',
                                            size: 'icon-sm',
                                        }),
                                        'shrink-0',
                                    )}
                                >
                                    <HugeiconsIcon icon={ArrowLeft01Icon} />
                                </Link>
                                <h1 className="min-w-0 flex-1 truncate text-xl font-semibold tracking-tight">
                                    {templateName}
                                </h1>
                                {canManage ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        className="shrink-0"
                                        aria-label="Rename template"
                                        data-test="rename-template"
                                        onClick={() =>
                                            setOpenSection('details')
                                        }
                                    >
                                        <HugeiconsIcon icon={Edit03Icon} />
                                    </Button>
                                ) : null}
                                <Badge variant="secondary" className="shrink-0">
                                    {EDITOR_LABELS[template.editor]}
                                </Badge>
                            </div>
                            {canManage && (
                                <div className="flex items-center justify-end gap-2 sm:shrink-0">
                                    {canUse && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            data-test="use-template-button"
                                            onClick={() => setUseOpen(true)}
                                        >
                                            <HugeiconsIcon
                                                icon={Mail01Icon}
                                                data-icon="inline-start"
                                            />
                                            Use
                                        </Button>
                                    )}
                                    <DropdownMenu>
                                        <DropdownMenuTrigger
                                            render={
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label="Template actions"
                                                    data-test="template-actions-button"
                                                />
                                            }
                                        >
                                            <HugeiconsIcon
                                                icon={MoreHorizontalIcon}
                                            />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            className="w-52"
                                        >
                                            <DropdownMenuGroup>
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    data-test="delete-template-button"
                                                    onClick={() =>
                                                        setDeleteOpen(true)
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Delete02Icon}
                                                    />
                                                    Delete template
                                                </DropdownMenuItem>
                                            </DropdownMenuGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            )}
                        </div>

                        <div className="overflow-hidden rounded-2xl border bg-card">
                            <div className="divide-y">
                                <CampaignSetupRow
                                    testId="template-setup-details"
                                    done={descriptionDone}
                                    title="Details"
                                    summary={
                                        descriptionDone
                                            ? form.data.description
                                            : 'How this template appears in the library.'
                                    }
                                    actionLabel="Edit details"
                                    hasError={errorFor('details')}
                                    disabled={!canManage}
                                    onAction={() => setOpenSection('details')}
                                />
                                <CampaignSetupRow
                                    testId="template-setup-subject"
                                    done={subjectDone}
                                    title="Subject"
                                    summary={
                                        subjectDone
                                            ? form.data.subject
                                            : 'Copied into campaigns started from this template.'
                                    }
                                    actionLabel={
                                        subjectDone
                                            ? 'Edit subject'
                                            : 'Add subject'
                                    }
                                    hasError={errorFor('subject')}
                                    disabled={!canManage}
                                    onAction={() => setOpenSection('subject')}
                                />
                                <CampaignSetupRow
                                    testId="template-setup-design"
                                    done={hasBody}
                                    title="Design"
                                    summary={
                                        hasBody
                                            ? undefined
                                            : 'Compose the email campaigns will start from.'
                                    }
                                    actionLabel={
                                        hasBody
                                            ? 'Edit design'
                                            : 'Start designing'
                                    }
                                    hasError={errorFor('design')}
                                    disabled={!canManage}
                                    onAction={() => setView('design')}
                                >
                                    {hasBody ? (
                                        <TemplateDesignPreview
                                            editor={template.editor}
                                            design={form.data.design}
                                            html={form.data.html}
                                            source={form.data.source}
                                        />
                                    ) : null}
                                </CampaignSetupRow>
                            </div>
                        </div>
                    </div>
                )}
            </form>

            <Dialog
                open={openSection === 'details'}
                onOpenChange={(open) => changeSection('details', open)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Template details</DialogTitle>
                        <DialogDescription>
                            How this template appears in the library. Only your
                            team sees this.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="name">Name</FieldLabel>
                            <Input
                                id="name"
                                data-test="email-template-name"
                                disabled={!canManage}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Weekly digest"
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.description)}>
                            <FieldLabel htmlFor="description">
                                Description
                            </FieldLabel>
                            <Input
                                id="description"
                                disabled={!canManage}
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Our usual layout"
                                aria-invalid={Boolean(form.errors.description)}
                            />
                            <FieldDescription>
                                Optional, shown in the compose picker.
                            </FieldDescription>
                            <FieldError>{form.errors.description}</FieldError>
                        </Field>
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            type="button"
                            data-test="save-template-details"
                            disabled={form.processing}
                            onClick={saveSection}
                        >
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={openSection === 'subject'}
                onOpenChange={(open) => changeSection('subject', open)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Subject</DialogTitle>
                        <DialogDescription>
                            Copied into campaigns started from this template.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.subject)}>
                            <FieldLabel htmlFor="subject">Subject</FieldLabel>
                            <Input
                                id="subject"
                                data-test="email-template-subject"
                                disabled={!canManage}
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                                placeholder="What's new this month"
                                aria-invalid={Boolean(form.errors.subject)}
                            />
                            <FieldError>{form.errors.subject}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.preheader)}>
                            <FieldLabel htmlFor="preheader">
                                Preheader
                            </FieldLabel>
                            <Input
                                id="preheader"
                                data-test="email-template-preheader"
                                disabled={!canManage}
                                value={form.data.preheader}
                                onChange={(event) =>
                                    form.setData(
                                        'preheader',
                                        event.target.value,
                                    )
                                }
                                placeholder="A quick look at this month's updates"
                                aria-invalid={Boolean(form.errors.preheader)}
                            />
                            <FieldDescription>
                                The preview line shown after the subject.
                            </FieldDescription>
                            <FieldError>{form.errors.preheader}</FieldError>
                        </Field>
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            type="button"
                            data-test="save-template-subject"
                            disabled={form.processing}
                            onClick={saveSection}
                        >
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {canManage && (
                <>
                    <EmailTemplatePicker
                        teamSlug={currentTeam.slug}
                        templates={templates}
                        defaultEditor={defaultEditor}
                        initialTemplate={template.uuid}
                        open={useOpen}
                        onOpenChange={setUseOpen}
                    />

                    <DeleteEmailTemplateModal
                        teamSlug={currentTeam.slug}
                        template={template}
                        open={deleteOpen}
                        onOpenChange={setDeleteOpen}
                    />
                </>
            )}
        </>
    );
}
