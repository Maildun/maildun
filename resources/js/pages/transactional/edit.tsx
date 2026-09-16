import {
    Add01Icon,
    ArrowLeft01Icon,
    Copy01Icon,
    Delete02Icon,
    Edit03Icon,
    MailSend01Icon,
    MoreHorizontalIcon,
    Upload01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Reader } from '@usewaypoint/email-builder';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { CampaignSetupRow } from '@/components/campaign-setup-row';
import DeleteTransactionalEmailModal from '@/components/delete-transactional-email-modal';
import { EmailBuilderEditor } from '@/components/email-builder-editor';
import { EmailHtmlEditor } from '@/components/email-html-editor';
import { EmailSourceEditor } from '@/components/email-source-editor';
import SendTestTransactionalEmailDialog from '@/components/send-test-transactional-email-dialog';
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
    DropdownMenuSeparator,
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
import { useClipboard } from '@/hooks/use-clipboard';
import {
    EMPTY_BUILDER_DOCUMENT,
    ROOT_BLOCK_ID,
    getChildrenIds,
    htmlToBuilderDocument,
    isSourceEditor,
    renderBuilderHtml,
    renderSourceHtml,
    sourceToBuilderDocument,
    toReaderDocument,
} from '@/lib/email-builder';
import { cn } from '@/lib/utils';
import {
    index,
    publish,
    unpublish,
    update,
} from '@/routes/transactional_emails';
import type {
    EmailBuilderDocument,
    EmailEditorMode,
    EmailSenderDefaults,
    TransactionalEmailDetail,
    TransactionalVariable,
} from '@/types';

type Props = {
    email: TransactionalEmailDetail;
    defaults: EmailSenderDefaults;
    canManage: boolean;
    currentTeam: { slug: string };
    auth: { user: { email: string } };
};

type SectionValue = 'details' | 'sender' | 'design' | 'variables';
type DialogSection = Exclude<SectionValue, 'design'>;

const SECTIONS: { value: SectionValue; fields: string[] }[] = [
    {
        value: 'details',
        fields: ['name', 'slug', 'description', 'subject', 'preheader'],
    },
    {
        value: 'sender',
        fields: ['from_name', 'from_address', 'reply_to'],
    },
    { value: 'design', fields: ['html', 'source', 'design'] },
    { value: 'variables', fields: ['variables'] },
];

const VARIABLE_KEY = /^[a-zA-Z_][a-zA-Z0-9_]*$/;

/** Filled in on every send, so it is never a variable the sender supplies. */
const BUILT_IN_TAGS = [
    {
        key: 'web_view_url',
        label: 'View in browser',
        description: 'Opens a hosted copy of the email that was sent.',
    },
] as const;

function TransactionalDesignPreview({
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
    let preview = null;

    if (editor === 'builder' && design) {
        preview = (
            <Reader
                document={toReaderDocument(design)}
                rootBlockId={ROOT_BLOCK_ID}
            />
        );
    } else if (isSourceEditor(editor) && source.trim() !== '') {
        preview = (
            <Reader
                document={toReaderDocument(
                    sourceToBuilderDocument(source, editor),
                )}
                rootBlockId={ROOT_BLOCK_ID}
            />
        );
    } else if (html.trim() !== '') {
        preview = (
            <iframe
                title="Transactional email design preview"
                sandbox=""
                srcDoc={html}
                tabIndex={-1}
                className="pointer-events-none h-full w-full border-0 bg-background"
            />
        );
    }

    if (!preview) {
        return null;
    }

    return (
        <div className="px-6 pb-6" data-test="transactional-design-preview">
            <div className="flex h-72 items-start justify-center overflow-hidden rounded-lg bg-muted">
                <div className="pointer-events-none h-[800px] w-[600px] shrink-0 origin-top scale-[0.48]">
                    {preview}
                </div>
            </div>
        </div>
    );
}

export default function TransactionalEdit({
    email,
    defaults,
    canManage,
    currentTeam,
    auth,
}: Props) {
    const [view, setView] = useState<'hub' | 'design'>('hub');
    const [openSection, setOpenSection] = useState<DialogSection | null>(null);
    const [testOpen, setTestOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [newVariableKey, setNewVariableKey] = useState('');
    const [, copy] = useClipboard();

    const form = useForm<{
        name: string;
        slug: string;
        description: string;
        subject: string;
        preheader: string;
        from_name: string;
        from_address: string;
        reply_to: string;
        html: string;
        source: string;
        design: EmailBuilderDocument | null;
        variables: TransactionalVariable[];
    }>({
        name: email.name,
        slug: email.slug,
        description: email.description ?? '',
        subject: email.subject,
        preheader: email.preheader ?? '',
        from_name: email.from_name ?? '',
        from_address: email.from_address ?? '',
        reply_to: email.reply_to ?? '',
        html: email.html ?? '',
        source: email.source,
        design:
            email.editor === 'builder'
                ? (email.design ?? htmlToBuilderDocument(email.html ?? ''))
                : null,
        variables: email.variables,
    });

    const errorFor = (value: SectionValue) =>
        SECTIONS.find((entry) => entry.value === value)?.fields.some((field) =>
            Object.keys(form.errors).some(
                (error) => error === field || error.startsWith(`${field}.`),
            ),
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

    const saveDraft = (onSuccess?: () => void) => {
        form.transform((data) => ({
            ...data,
            html:
                email.editor === 'builder' && data.design
                    ? renderBuilderHtml(data.design)
                    : email.editor === 'plain_text' ||
                        email.editor === 'markdown'
                      ? renderSourceHtml(data.source, email.editor)
                      : data.html,
            source:
                email.editor === 'plain_text' || email.editor === 'markdown'
                    ? data.source
                    : '',
            design: email.editor === 'builder' ? data.design : null,
        }));

        form.patch(update.url([currentTeam.slug, email.uuid]), {
            preserveScroll: true,
            onSuccess: () => {
                form.setDefaults();
                onSuccess?.();
            },
            onError: (errors) => {
                const firstSection = SECTIONS.find((entry) =>
                    entry.fields.some((field) =>
                        Object.keys(errors).some(
                            (error) =>
                                error === field ||
                                error.startsWith(`${field}.`),
                        ),
                    ),
                );

                if (firstSection) {
                    revealSection(firstSection.value);
                }
            },
        });
    };

    const save = (event: FormEvent) => {
        event.preventDefault();
        saveDraft();
    };

    const saveSection = () => {
        saveDraft(() => setOpenSection(null));
    };

    const hasBody =
        email.editor === 'builder'
            ? form.data.design !== null &&
              getChildrenIds(form.data.design).length > 0
            : isSourceEditor(email.editor)
              ? form.data.source.trim() !== ''
              : form.data.html.trim() !== '';
    const detailsDone =
        form.data.name.trim() !== '' &&
        form.data.slug.trim() !== '' &&
        form.data.subject.trim() !== '';
    const senderName = form.data.from_name || defaults.from_name;
    const senderAddress = form.data.from_address || defaults.from_address;
    const senderDone = Boolean(senderAddress);
    const senderSummary = senderAddress
        ? [senderName, senderAddress].filter(Boolean).join(' · ')
        : 'Use workspace sender defaults';
    const detailsSummary = [
        form.data.subject.trim() || null,
        form.data.slug.trim() ? `Identifier: ${form.data.slug}` : null,
    ].filter(Boolean);
    const variablesSummary =
        form.data.variables.length > 0
            ? `${form.data.variables.length} variable${form.data.variables.length === 1 ? '' : 's'} configured`
            : 'Add merge tags as you write the subject or email.';
    const readyToPublish =
        form.data.subject.trim() !== '' &&
        form.data.slug.trim() !== '' &&
        hasBody &&
        !form.isDirty;
    const published = email.status === 'published';
    const designing = view === 'design';

    const addVariable = () => {
        const key = newVariableKey.trim();

        if (!VARIABLE_KEY.test(key)) {
            return;
        }

        if (
            form.data.variables.some((variable) => variable.key === key) ||
            BUILT_IN_TAGS.some((tag) => tag.key === key)
        ) {
            setNewVariableKey('');

            return;
        }

        form.setData('variables', [
            ...form.data.variables,
            { key, example: '' },
        ]);
        setNewVariableKey('');
    };

    const variableError = (index: number, field: 'key' | 'example') =>
        form.errors[`variables.${index}.${field}` as keyof typeof form.errors];

    setLayoutProps({ fullscreen: designing });

    return (
        <>
            <Head title={`Edit ${form.data.name || email.name}`} />

            <form
                onSubmit={save}
                className={cn(
                    designing
                        ? 'relative flex h-dvh min-h-0 w-full flex-col overflow-hidden bg-background'
                        : 'flex min-w-0 flex-col gap-6',
                )}
                data-test={
                    designing
                        ? 'transactional-design-shell'
                        : 'transactional-setup-hub'
                }
            >
                {designing ? (
                    <>
                        <header
                            className="flex h-14 shrink-0 items-center justify-between gap-3 border-b bg-background px-3 sm:px-4"
                            data-test="transactional-design-navbar"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Back to transactional email setup"
                                    data-test="transactional-design-back"
                                    onClick={() => setView('hub')}
                                >
                                    <HugeiconsIcon icon={ArrowLeft01Icon} />
                                </Button>
                                <Separator
                                    orientation="vertical"
                                    className="hidden h-5 sm:block"
                                />
                                <span className="truncate font-medium">
                                    {form.data.name || email.name}
                                </span>
                                <Badge
                                    variant={
                                        published ? 'success' : 'secondary'
                                    }
                                >
                                    {published ? 'Published' : 'Draft'}
                                </Badge>
                            </div>
                            {canManage ? (
                                <Button
                                    type="submit"
                                    size="sm"
                                    data-test="save-transactional-button"
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
                            {email.editor === 'builder' ? (
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
                            ) : email.editor === 'html' ? (
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
                                        editor={email.editor}
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
                                    aria-label="Back to transactional emails"
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
                                    {form.data.name || email.name}
                                </h1>
                                {canManage ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        className="shrink-0"
                                        aria-label="Rename transactional email"
                                        data-test="rename-transactional-email"
                                        onClick={() =>
                                            setOpenSection('details')
                                        }
                                    >
                                        <HugeiconsIcon icon={Edit03Icon} />
                                    </Button>
                                ) : null}
                                <Badge
                                    variant={
                                        published ? 'success' : 'secondary'
                                    }
                                    className="shrink-0"
                                >
                                    {published ? 'Published' : 'Draft'}
                                </Badge>
                            </div>
                            {canManage && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger
                                        render={
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Transactional email actions"
                                                data-test="transactional-actions-button"
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
                                                data-test="send-test-button"
                                                onClick={() =>
                                                    setTestOpen(true)
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={MailSend01Icon}
                                                />
                                                Send test
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                data-test={
                                                    published
                                                        ? 'unpublish-transactional-button'
                                                        : 'publish-transactional-button'
                                                }
                                                disabled={
                                                    publishing ||
                                                    (!published &&
                                                        !readyToPublish)
                                                }
                                                onClick={() =>
                                                    router.post(
                                                        (published
                                                            ? unpublish
                                                            : publish
                                                        ).url([
                                                            currentTeam.slug,
                                                            email.uuid,
                                                        ]),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            onStart: () =>
                                                                setPublishing(
                                                                    true,
                                                                ),
                                                            onFinish: () =>
                                                                setPublishing(
                                                                    false,
                                                                ),
                                                        },
                                                    )
                                                }
                                            >
                                                {publishing ? (
                                                    <Spinner />
                                                ) : (
                                                    <HugeiconsIcon
                                                        icon={Upload01Icon}
                                                    />
                                                )}
                                                {published
                                                    ? 'Unpublish'
                                                    : 'Publish'}
                                            </DropdownMenuItem>
                                        </DropdownMenuGroup>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            aria-label="Delete transactional email"
                                            data-test="delete-transactional-button"
                                            onClick={() => setDeleteOpen(true)}
                                        >
                                            <HugeiconsIcon
                                                icon={Delete02Icon}
                                            />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>

                        <div
                            className="overflow-hidden rounded-2xl border bg-card"
                            data-test="transactional-setup-rows"
                        >
                            <div className="divide-y">
                                <CampaignSetupRow
                                    testId="transactional-setup-details"
                                    done={detailsDone}
                                    title="Details"
                                    summary={
                                        detailsSummary.length > 0
                                            ? detailsSummary.join(' · ')
                                            : 'Add a subject line and API identifier.'
                                    }
                                    actionLabel={
                                        detailsDone
                                            ? 'Edit details'
                                            : 'Add details'
                                    }
                                    hasError={errorFor('details')}
                                    disabled={!canManage}
                                    onAction={() => setOpenSection('details')}
                                />
                                <CampaignSetupRow
                                    testId="transactional-setup-sender"
                                    done={senderDone}
                                    title="Sender"
                                    summary={senderSummary}
                                    actionLabel="Manage sender"
                                    hasError={errorFor('sender')}
                                    disabled={!canManage}
                                    onAction={() => setOpenSection('sender')}
                                />
                                <CampaignSetupRow
                                    testId="transactional-setup-design"
                                    done={hasBody}
                                    title="Content"
                                    summary={
                                        hasBody
                                            ? undefined
                                            : 'Write the email content.'
                                    }
                                    actionLabel={
                                        hasBody
                                            ? 'Edit content'
                                            : 'Start writing'
                                    }
                                    hasError={errorFor('design')}
                                    disabled={!canManage}
                                    onAction={() => setView('design')}
                                >
                                    {hasBody ? (
                                        <TransactionalDesignPreview
                                            editor={email.editor}
                                            design={form.data.design}
                                            html={form.data.html}
                                            source={form.data.source}
                                        />
                                    ) : null}
                                </CampaignSetupRow>
                                <CampaignSetupRow
                                    testId="transactional-setup-variables"
                                    done={form.data.variables.length > 0}
                                    title="Variables"
                                    summary={variablesSummary}
                                    actionLabel="Manage variables"
                                    hasError={errorFor('variables')}
                                    disabled={!canManage}
                                    onAction={() => setOpenSection('variables')}
                                />
                            </div>
                        </div>
                    </div>
                )}
            </form>

            <Dialog
                open={openSection === 'details'}
                onOpenChange={(open) => setOpenSection(open ? 'details' : null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Transactional email details</DialogTitle>
                        <DialogDescription>
                            Set the internal name, API identifier, and inbox
                            preview details.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup className="gap-5">
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="transactional-name">
                                Internal name
                            </FieldLabel>
                            <Input
                                id="transactional-name"
                                data-test="transactional-name"
                                disabled={!canManage}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Welcome email"
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <FieldDescription>
                                Only your team sees this.
                            </FieldDescription>
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.slug)}>
                            <FieldLabel htmlFor="transactional-slug">
                                Identifier
                            </FieldLabel>
                            <div className="flex gap-2">
                                <Input
                                    id="transactional-slug"
                                    data-test="transactional-slug"
                                    disabled={!canManage || email.slug_frozen}
                                    value={form.data.slug}
                                    onChange={(event) =>
                                        form.setData('slug', event.target.value)
                                    }
                                    placeholder="welcome-email"
                                    aria-invalid={Boolean(form.errors.slug)}
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    aria-label="Copy identifier"
                                    onClick={() => copy(form.data.slug)}
                                >
                                    <HugeiconsIcon icon={Copy01Icon} />
                                </Button>
                            </div>
                            <FieldDescription>
                                {email.slug_frozen
                                    ? 'Frozen after the first publish so the API can keep calling it.'
                                    : 'Lowercase letters, numbers, and hyphens. Frozen after the first publish.'}
                            </FieldDescription>
                            <FieldError>{form.errors.slug}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.subject)}>
                            <FieldLabel htmlFor="transactional-subject">
                                Subject
                            </FieldLabel>
                            <Input
                                id="transactional-subject"
                                data-test="transactional-subject"
                                disabled={!canManage}
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                                placeholder="Welcome, {{ first_name }}"
                                aria-invalid={Boolean(form.errors.subject)}
                            />
                            <FieldError>{form.errors.subject}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.preheader)}>
                            <FieldLabel htmlFor="transactional-preheader">
                                Preheader
                            </FieldLabel>
                            <Input
                                id="transactional-preheader"
                                disabled={!canManage}
                                value={form.data.preheader}
                                onChange={(event) =>
                                    form.setData(
                                        'preheader',
                                        event.target.value,
                                    )
                                }
                                placeholder="A quick look at what's next"
                                aria-invalid={Boolean(form.errors.preheader)}
                            />
                            <FieldDescription>
                                The preview line shown after the subject.
                            </FieldDescription>
                            <FieldError>{form.errors.preheader}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.description)}>
                            <FieldLabel htmlFor="transactional-description">
                                Description
                            </FieldLabel>
                            <Input
                                id="transactional-description"
                                disabled={!canManage}
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Sent when someone creates an account"
                                aria-invalid={Boolean(form.errors.description)}
                            />
                            <FieldError>{form.errors.description}</FieldError>
                        </Field>
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            type="button"
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
                open={openSection === 'sender'}
                onOpenChange={(open) => setOpenSection(open ? 'sender' : null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Sender</DialogTitle>
                        <DialogDescription>
                            Leave blank to use the workspace sender defaults.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup className="gap-5">
                        <Field data-invalid={Boolean(form.errors.from_name)}>
                            <FieldLabel htmlFor="transactional-from-name">
                                From name
                            </FieldLabel>
                            <Input
                                id="transactional-from-name"
                                disabled={!canManage}
                                placeholder={
                                    defaults.from_name ?? 'Acme Support'
                                }
                                value={form.data.from_name}
                                onChange={(event) =>
                                    form.setData(
                                        'from_name',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.from_name)}
                            />
                            <FieldError>{form.errors.from_name}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.from_address)}>
                            <FieldLabel htmlFor="transactional-from-address">
                                From address
                            </FieldLabel>
                            <Input
                                id="transactional-from-address"
                                type="email"
                                disabled={!canManage}
                                placeholder={
                                    defaults.from_address ?? 'hello@example.com'
                                }
                                value={form.data.from_address}
                                onChange={(event) =>
                                    form.setData(
                                        'from_address',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.from_address)}
                            />
                            <FieldDescription>
                                Must match a workspace sender verified for the
                                current email delivery connection.
                            </FieldDescription>
                            <FieldError>{form.errors.from_address}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.reply_to)}>
                            <FieldLabel htmlFor="transactional-reply-to">
                                Reply-to
                            </FieldLabel>
                            <Input
                                id="transactional-reply-to"
                                type="email"
                                disabled={!canManage}
                                placeholder={
                                    defaults.reply_to ?? 'replies@example.com'
                                }
                                value={form.data.reply_to}
                                onChange={(event) =>
                                    form.setData('reply_to', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.reply_to)}
                            />
                            <FieldError>{form.errors.reply_to}</FieldError>
                        </Field>
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            type="button"
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
                open={openSection === 'variables'}
                onOpenChange={(open) =>
                    setOpenSection(open ? 'variables' : null)
                }
            >
                <DialogContent className="max-h-[min(40rem,calc(100vh-4rem))] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Variables</DialogTitle>
                        <DialogDescription>
                            Use {'{{ first_name }}'} in the subject or body.
                            Saving adds any keys it finds in the content.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup className="gap-5">
                        {BUILT_IN_TAGS.map((tag) => (
                            <Field
                                key={tag.key}
                                data-test={`transactional-built-in-${tag.key}`}
                            >
                                <FieldLabel htmlFor={`built-in-tag-${tag.key}`}>
                                    {tag.label}
                                </FieldLabel>
                                <div className="flex gap-2">
                                    <Input
                                        id={`built-in-tag-${tag.key}`}
                                        readOnly
                                        value={`{{ ${tag.key} }}`}
                                        placeholder={`{{ ${tag.key} }}`}
                                        className="font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        aria-label={`Copy {{ ${tag.key} }}`}
                                        onClick={() => copy(`{{ ${tag.key} }}`)}
                                    >
                                        <HugeiconsIcon icon={Copy01Icon} />
                                    </Button>
                                </div>
                                <FieldDescription>
                                    {tag.description} Filled in automatically,
                                    so it needs no value.
                                </FieldDescription>
                            </Field>
                        ))}

                        {form.data.variables.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No variables yet. Add one below or write a merge
                                tag in the content and save.
                            </p>
                        ) : (
                            form.data.variables.map((variable, index) => (
                                <Field
                                    key={variable.key || index}
                                    data-invalid={Boolean(
                                        variableError(index, 'key') ||
                                        variableError(index, 'example'),
                                    )}
                                >
                                    <FieldLabel
                                        htmlFor={`transactional-variable-example-${index}`}
                                    >
                                        {variable.key}
                                    </FieldLabel>
                                    <div className="flex gap-2">
                                        <Input
                                            id={`transactional-variable-example-${index}`}
                                            disabled={!canManage}
                                            value={variable.example}
                                            onChange={(event) => {
                                                const next = [
                                                    ...form.data.variables,
                                                ];
                                                next[index] = {
                                                    ...variable,
                                                    example: event.target.value,
                                                };
                                                form.setData('variables', next);
                                            }}
                                            placeholder="Ada"
                                            aria-invalid={Boolean(
                                                variableError(index, 'example'),
                                            )}
                                        />
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="outline"
                                            aria-label={`Copy {{ ${variable.key} }}`}
                                            onClick={() =>
                                                copy(`{{ ${variable.key} }}`)
                                            }
                                        >
                                            <HugeiconsIcon icon={Copy01Icon} />
                                        </Button>
                                        {canManage && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                aria-label={`Remove ${variable.key}`}
                                                onClick={() =>
                                                    form.setData(
                                                        'variables',
                                                        form.data.variables.filter(
                                                            (_, current) =>
                                                                current !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={Delete02Icon}
                                                />
                                            </Button>
                                        )}
                                    </div>
                                    <FieldError>
                                        {variableError(index, 'key') ??
                                            variableError(index, 'example')}
                                    </FieldError>
                                </Field>
                            ))
                        )}

                        {canManage && (
                            <Field>
                                <FieldLabel htmlFor="new-variable-key">
                                    Add a variable
                                </FieldLabel>
                                <div className="flex gap-2">
                                    <Input
                                        id="new-variable-key"
                                        data-test="transactional-variable-key"
                                        value={newVariableKey}
                                        onChange={(event) =>
                                            setNewVariableKey(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="first_name"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        data-test="add-transactional-variable"
                                        disabled={
                                            !VARIABLE_KEY.test(
                                                newVariableKey.trim(),
                                            )
                                        }
                                        onClick={addVariable}
                                    >
                                        <HugeiconsIcon
                                            icon={Add01Icon}
                                            data-icon="inline-start"
                                        />
                                        Add
                                    </Button>
                                </div>
                                <FieldDescription>
                                    Letters, numbers, and underscores. Saving
                                    re-adds keys still used in the subject or
                                    body.
                                </FieldDescription>
                            </Field>
                        )}
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            type="button"
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
                    <SendTestTransactionalEmailDialog
                        teamSlug={currentTeam.slug}
                        emailUuid={email.uuid}
                        defaultAddress={auth.user.email}
                        variables={form.data.variables}
                        open={testOpen}
                        onOpenChange={setTestOpen}
                    />

                    <DeleteTransactionalEmailModal
                        teamSlug={currentTeam.slug}
                        email={email}
                        open={deleteOpen}
                        onOpenChange={setDeleteOpen}
                    />
                </>
            )}
        </>
    );
}
