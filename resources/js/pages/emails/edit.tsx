import {
    Alert01Icon,
    ArrowLeft01Icon,
    Attachment01Icon,
    CheckmarkCircle02Icon,
    Copy01Icon,
    Delete02Icon,
    Edit03Icon,
    File01Icon,
    FolderLibraryIcon,
    Link01Icon,
    MoreHorizontalIcon,
    TagsIcon,
    Upload01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    useHttp,
} from '@inertiajs/react';
import { Reader } from '@usewaypoint/email-builder';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';
import { CampaignSetupRow } from '@/components/campaign-setup-row';
import DeleteEmailModal from '@/components/delete-email-modal';
import { EmailBuilderEditor } from '@/components/email-builder-editor';
import { EmailHtmlEditor } from '@/components/email-html-editor';
import { EmailSourceEditor } from '@/components/email-source-editor';
import MediaLibraryDialog from '@/components/media-library-dialog';
import SaveEmailAsTemplateDialog from '@/components/save-email-as-template-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldContent,
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
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { toast } from '@/components/ui/toast';
import {
    firstUploadErrorMessage,
    useUploadToast,
} from '@/hooks/use-upload-toast';
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
import { checkLinks, index, previewAndSend, update } from '@/routes/emails';
import {
    destroy as destroyAttachment,
    store as storeAttachments,
} from '@/routes/emails/attachments';
import { edit as editWorkspaceSenders } from '@/routes/teams/sender';
import type {
    EmailAudienceOption,
    EmailBuilderDocument,
    EmailDetail,
    EmailEditorMode,
    EmailSenderDefaults,
    EmailSenderOption,
    MediaLibraryData,
} from '@/types';

type Props = {
    email: EmailDetail;
    audiences: EmailAudienceOption[];
    defaults: EmailSenderDefaults;
    senders: EmailSenderOption[];
    selectedSenderUuid: string | null;
    canManage: boolean;
    currentTeam: { slug: string };
    mediaLibrary: MediaLibraryData | null;
};

/** The Select primitive has no "empty" value, so absence gets its own key. */
const NONE = '__none__';
const AUDIENCE_DEFAULT_SENDER = 'audience-default';
const CURRENT_SENDER = 'current-sender';

const ATTACHMENT_ACCEPT =
    'image/jpeg,image/gif,image/png,application/pdf,application/zip,.jpeg,.jpg,.gif,.png,.pdf,.zip';

const PERSONALIZATION_TAGS = [
    { label: 'Name', tag: '{{ name }}' },
    { label: 'Email', tag: '{{ email }}' },
    { label: 'Two digit day', tag: '{{ day }}' },
    { label: 'Full day name', tag: '{{ day_name }}' },
    { label: 'Two digit month', tag: '{{ month }}' },
    { label: 'Full month name', tag: '{{ month_name }}' },
    { label: 'Four digit year', tag: '{{ year }}' },
] as const;

/** Filled in per delivery when the campaign is sent. */
const LINK_TAGS = [
    { label: 'View in browser', tag: '{{ web_view_url }}' },
    { label: 'Unsubscribe', tag: '{{ unsubscribe_url }}' },
] as const;

type LinkCheckResponse = {
    checked: number;
    broken: { url: string; status: number | null; reason: string }[];
};

type SectionValue =
    'name' | 'sender' | 'recipients' | 'subject' | 'design' | 'settings';

type DialogSection = Exclude<SectionValue, 'design'>;

const SECTIONS: { value: SectionValue; fields: string[] }[] = [
    { value: 'name', fields: ['name'] },
    {
        value: 'sender',
        fields: ['sender_uuid'],
    },
    { value: 'recipients', fields: ['audience', 'segment'] },
    { value: 'subject', fields: ['subject', 'preheader'] },
    { value: 'design', fields: ['html', 'source', 'design'] },
    {
        value: 'settings',
        fields: [
            'plain_text',
            'query_string',
            'track_clicks',
            'track_opens',
            'attachments',
        ],
    },
];

function CampaignDesignPreview({
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
                title="Campaign design preview"
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
        <div className="px-6 pb-6" data-test="campaign-design-preview">
            <div className="flex h-72 items-start justify-center overflow-hidden rounded-lg bg-muted">
                <div className="pointer-events-none h-[800px] w-[600px] shrink-0 origin-top scale-[0.48]">
                    {preview}
                </div>
            </div>
        </div>
    );
}

export default function EmailEdit({
    email,
    audiences,
    defaults,
    senders,
    selectedSenderUuid,
    canManage,
    currentTeam,
    mediaLibrary,
}: Props) {
    const [view, setView] = useState<'hub' | 'design'>('hub');
    const [openSection, setOpenSection] = useState<DialogSection | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [mediaDialogOpen, setMediaDialogOpen] = useState(false);
    const [uploadingAttachments, setUploadingAttachments] = useState(false);
    const [linkCheckResult, setLinkCheckResult] =
        useState<LinkCheckResponse | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const uploadToast = useUploadToast();
    const linkCheck = useHttp<Record<string, never>, LinkCheckResponse>({});
    const [templateBody, setTemplateBody] = useState<{
        html: string;
        source: string;
        design: EmailBuilderDocument | null;
        subject: string;
        preheader: string;
    } | null>(null);

    const form = useForm<{
        name: string;
        subject: string;
        preheader: string;
        sender_uuid: string | null;
        html: string;
        source: string;
        plain_text: string;
        query_string: string;
        track_clicks: boolean;
        track_opens: boolean;
        attachments: string;
        design: EmailBuilderDocument | null;
        audience: string | null;
        segment: string | null;
    }>({
        name: email.name,
        subject: email.subject,
        preheader: email.preheader ?? '',
        sender_uuid: selectedSenderUuid,
        html: email.html ?? '',
        source: email.source,
        plain_text: email.plain_text,
        query_string: email.query_string,
        track_clicks: email.track_clicks,
        track_opens: email.track_opens,
        attachments: '',
        design:
            email.editor === 'builder'
                ? (email.design ?? htmlToBuilderDocument(email.html ?? ''))
                : null,
        audience: email.audience,
        segment: email.segment,
    });

    const selectedAudience = audiences.find(
        (audience) => audience.uuid === form.data.audience,
    );
    const selectedSegment = selectedAudience?.segments.find(
        (segment) => segment.uuid === form.data.segment,
    );

    const copyTag = (tag: string) => {
        void navigator.clipboard.writeText(tag);
        toast.add({ type: 'success', title: `${tag} copied.` });
    };

    const uploadAttachments = (files: FileList) => {
        if (files.length === 0 || uploadingAttachments) {
            return;
        }

        router.post(
            storeAttachments.url([currentTeam.slug, email.uuid]),
            { attachments: Array.from(files) },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                only: ['email'],
                onStart: () => {
                    setUploadingAttachments(true);
                    uploadToast.begin({
                        title:
                            files.length === 1
                                ? 'Uploading attachment…'
                                : `Uploading ${files.length} attachments…`,
                        files: Array.from(files),
                    });
                },
                onProgress: (progress) => uploadToast.setProgress(progress),
                onSuccess: () => {
                    form.clearErrors('attachments');
                    uploadToast.succeed({
                        title:
                            files.length === 1
                                ? 'Attachment uploaded.'
                                : `${files.length} attachments uploaded.`,
                    });
                },
                onError: (errors) => {
                    const message = Object.entries(errors).find(([key]) =>
                        key.startsWith('attachments'),
                    )?.[1];

                    form.setError(
                        'attachments',
                        typeof message === 'string'
                            ? message
                            : 'Failed to upload attachments.',
                    );
                    uploadToast.fail({
                        title: firstUploadErrorMessage(
                            errors,
                            'Failed to upload attachments.',
                        ),
                    });
                },
                onHttpException: () => {
                    uploadToast.fail({
                        title: 'Failed to upload attachments.',
                    });
                },
                onNetworkError: () => {
                    uploadToast.fail({
                        title: 'Failed to upload attachments.',
                    });
                },
                onCancel: () =>
                    uploadToast.fail({ title: 'Upload cancelled.' }),
                onFinish: () => {
                    setUploadingAttachments(false);

                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
            },
        );
    };

    const removeAttachment = (uuid: string) => {
        router.delete(
            destroyAttachment.url([currentTeam.slug, email.uuid, uuid]),
            {
                preserveScroll: true,
                preserveState: true,
                only: ['email'],
                onSuccess: () =>
                    toast.add({
                        type: 'success',
                        title: 'Attachment removed.',
                    }),
            },
        );
    };

    const runLinkCheck = () => {
        void linkCheck
            .get(checkLinks.url([currentTeam.slug, email.uuid]), {
                onSuccess: setLinkCheckResult,
            })
            .catch(() => {
                toast.add({
                    type: 'error',
                    title: 'Links could not be checked.',
                });
            });
    };

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

    const saveDraft = (onSuccess?: () => void) => {
        setLinkCheckResult(null);

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
            plain_text:
                email.editor === 'plain_text' ? data.source : data.plain_text,
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
        saveDraft();
    };

    const saveSection = () => {
        saveDraft(() => setOpenSection(null));
    };

    const openTemplateDialog = () => {
        const isBuilder = email.editor === 'builder';
        const sourceEditor = isSourceEditor(email.editor) ? email.editor : null;

        setTemplateBody({
            html:
                isBuilder && form.data.design
                    ? renderBuilderHtml(form.data.design)
                    : sourceEditor
                      ? renderSourceHtml(form.data.source, sourceEditor)
                      : form.data.html,
            source: sourceEditor ? form.data.source : '',
            design: isBuilder ? form.data.design : null,
            subject: form.data.subject,
            preheader: form.data.preheader,
        });
    };

    const recipientCount = selectedSegment
        ? selectedSegment.subscribed_count
        : (selectedAudience?.subscribed_count ?? 0);

    const hasBody =
        email.editor === 'builder'
            ? form.data.design !== null &&
              getChildrenIds(form.data.design).length > 0
            : email.editor === 'plain_text' || email.editor === 'markdown'
              ? form.data.source.trim() !== ''
              : form.data.html.trim() !== '';

    const selectedSender = senders.find(
        (sender) => sender.uuid === form.data.sender_uuid,
    );
    const audienceSenderName =
        selectedAudience?.from_name ?? defaults.from_name;
    const audienceSenderAddress =
        selectedAudience?.from_address ?? defaults.from_address;
    const senderName =
        form.data.sender_uuid === AUDIENCE_DEFAULT_SENDER
            ? audienceSenderName
            : form.data.sender_uuid === CURRENT_SENDER
              ? email.from_name
              : selectedSender?.name;
    const senderAddress =
        form.data.sender_uuid === AUDIENCE_DEFAULT_SENDER
            ? audienceSenderAddress
            : form.data.sender_uuid === CURRENT_SENDER
              ? email.from_address
              : selectedSender?.email;
    const senderDone = Boolean(senderAddress);
    const recipientsDone = form.data.audience !== null && recipientCount > 0;
    const subjectDone = form.data.subject.trim() !== '';
    const readyToSend =
        subjectDone &&
        hasBody &&
        form.data.audience !== null &&
        recipientCount > 0 &&
        !form.isDirty;

    const senderSummary = senderAddress
        ? [senderName, senderAddress].filter(Boolean).join(' · ')
        : 'Select a verified sender';
    const audienceDefaultSender = audienceSenderAddress
        ? `${audienceSenderName ? `${audienceSenderName} — ` : ''}${audienceSenderAddress}`
        : 'Not configured';
    const senderItems = [
        {
            label: `${selectedAudience ? `${selectedAudience.name} default` : 'Audience default'} — ${audienceDefaultSender}`,
            value: AUDIENCE_DEFAULT_SENDER,
        },
        ...(form.data.sender_uuid === CURRENT_SENDER
            ? [
                  {
                      label: `Current sender — ${[email.from_name, email.from_address].filter(Boolean).join(' — ') || 'Custom sender'}`,
                      value: CURRENT_SENDER,
                  },
              ]
            : []),
        ...senders.map((sender) => ({
            label: sender.name
                ? `${sender.name} — ${sender.email}`
                : sender.email,
            value: sender.uuid,
        })),
    ];
    const recipientsSummary = selectedAudience
        ? `${selectedAudience.name}${selectedSegment ? ` · ${selectedSegment.name}` : ''} · ${recipientCount} ${recipientCount === 1 ? 'person' : 'people'}`
        : 'The people who receive your campaign';
    const subjectSummary = subjectDone
        ? form.data.subject
        : 'Add a subject line for this campaign.';
    const designSummary = hasBody
        ? 'Email content ready'
        : 'Create your email content.';
    const settingsBits = [
        email.attachments.length > 0
            ? `${email.attachments.length} attachment${email.attachments.length === 1 ? '' : 's'}`
            : null,
        form.data.track_opens ? 'Opens tracked' : null,
        form.data.track_clicks ? 'Clicks tracked' : null,
    ].filter(Boolean);
    const settingsSummary =
        settingsBits.length > 0
            ? settingsBits.join(' · ')
            : 'Plain text, tracking, and attachments';

    const designing = view === 'design';

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
                    designing ? 'campaign-design-shell' : 'campaign-setup-hub'
                }
            >
                {designing ? (
                    <>
                        <header
                            className="flex h-14 shrink-0 items-center justify-between gap-3 border-b bg-background px-3 sm:px-4"
                            data-test="campaign-design-navbar"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Back to campaign setup"
                                    data-test="campaign-design-back"
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
                                <Badge variant="secondary">Draft</Badge>
                            </div>
                            <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                {email.editor === 'builder' ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        aria-label="Open media library"
                                        data-test="open-media-library"
                                        onClick={() => setMediaDialogOpen(true)}
                                    >
                                        <HugeiconsIcon
                                            icon={FolderLibraryIcon}
                                            data-icon="inline-start"
                                        />
                                        <span className="hidden lg:inline">
                                            Open media
                                        </span>
                                    </Button>
                                ) : null}
                                <DropdownMenu>
                                    <DropdownMenuTrigger
                                        render={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={!canManage}
                                                data-test="personalization-tags"
                                            />
                                        }
                                    >
                                        <HugeiconsIcon
                                            icon={TagsIcon}
                                            data-icon="inline-start"
                                        />
                                        Personalization tags
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="w-72"
                                    >
                                        <DropdownMenuGroup>
                                            <DropdownMenuLabel>
                                                Subscriber and date
                                            </DropdownMenuLabel>
                                            {PERSONALIZATION_TAGS.map(
                                                (item) => (
                                                    <DropdownMenuItem
                                                        key={item.tag}
                                                        onClick={() =>
                                                            copyTag(item.tag)
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={Copy01Icon}
                                                        />
                                                        <span className="flex-1">
                                                            {item.label}
                                                        </span>
                                                        <code className="text-xs text-muted-foreground">
                                                            {item.tag}
                                                        </code>
                                                    </DropdownMenuItem>
                                                ),
                                            )}
                                        </DropdownMenuGroup>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuGroup>
                                            <DropdownMenuLabel>
                                                Links
                                            </DropdownMenuLabel>
                                            {LINK_TAGS.map((item) => (
                                                <DropdownMenuItem
                                                    key={item.tag}
                                                    onClick={() =>
                                                        copyTag(item.tag)
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Copy01Icon}
                                                    />
                                                    <span className="flex-1">
                                                        {item.label}
                                                    </span>
                                                    <code className="text-xs text-muted-foreground">
                                                        {item.tag}
                                                    </code>
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuGroup>
                                        {selectedAudience?.attributes.length ? (
                                            <>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuGroup>
                                                    <DropdownMenuLabel>
                                                        {selectedAudience.name}{' '}
                                                        custom fields
                                                    </DropdownMenuLabel>
                                                    {selectedAudience.attributes.map(
                                                        (attribute) => {
                                                            const tag = `{{ ${attribute.key} }}`;

                                                            return (
                                                                <DropdownMenuItem
                                                                    key={
                                                                        attribute.key
                                                                    }
                                                                    onClick={() =>
                                                                        copyTag(
                                                                            tag,
                                                                        )
                                                                    }
                                                                >
                                                                    <HugeiconsIcon
                                                                        icon={
                                                                            Copy01Icon
                                                                        }
                                                                    />
                                                                    <span className="flex-1">
                                                                        {
                                                                            attribute.name
                                                                        }
                                                                    </span>
                                                                    <code className="text-xs text-muted-foreground">
                                                                        {tag}
                                                                    </code>
                                                                </DropdownMenuItem>
                                                            );
                                                        },
                                                    )}
                                                </DropdownMenuGroup>
                                            </>
                                        ) : null}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                {canManage ? (
                                    <Button
                                        type="submit"
                                        size="sm"
                                        data-test="save-email-button"
                                        disabled={form.processing}
                                    >
                                        {form.processing && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        Save
                                    </Button>
                                ) : null}
                            </div>
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
                                    aria-label="Back to campaigns"
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
                                        aria-label="Rename campaign"
                                        data-test="rename-campaign"
                                        onClick={() => setOpenSection('name')}
                                    >
                                        <HugeiconsIcon icon={Edit03Icon} />
                                    </Button>
                                ) : null}
                                <Badge variant="secondary" className="shrink-0">
                                    Draft
                                </Badge>
                            </div>
                            <div className="flex flex-wrap items-center justify-end gap-2 sm:shrink-0 sm:flex-nowrap">
                                {canManage && (
                                    <>
                                        {readyToSend ? (
                                            <Link
                                                href={previewAndSend([
                                                    currentTeam.slug,
                                                    email.uuid,
                                                ])}
                                                className={buttonVariants()}
                                                data-test="preview-and-send-button"
                                            >
                                                Preview and Send
                                            </Link>
                                        ) : (
                                            <Button
                                                type="button"
                                                disabled
                                                data-test="preview-and-send-button"
                                            >
                                                Preview and Send
                                            </Button>
                                        )}
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                render={
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label="Campaign actions"
                                                        data-test="campaign-hub-overflow"
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
                                                        data-test="save-as-template-button"
                                                        onClick={
                                                            openTemplateDialog
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={File01Icon}
                                                        />
                                                        Save as template
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        data-test="delete-email-button"
                                                        onClick={() =>
                                                            setDeleteOpen(true)
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={Delete02Icon}
                                                        />
                                                        Delete campaign
                                                    </DropdownMenuItem>
                                                </DropdownMenuGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </>
                                )}
                            </div>
                        </div>
                        <div className="overflow-hidden rounded-2xl border bg-card">
                            <div className="divide-y">
                                <CampaignSetupRow
                                    testId="campaign-setup-sender"
                                    done={senderDone}
                                    title="Sender"
                                    summary={
                                        senderDone ? (
                                            <>
                                                {senderName ? (
                                                    <span className="font-medium text-foreground">
                                                        {senderName}
                                                    </span>
                                                ) : null}
                                                {senderName && senderAddress
                                                    ? ' · '
                                                    : null}
                                                {senderAddress}
                                            </>
                                        ) : (
                                            senderSummary
                                        )
                                    }
                                    actionLabel="Manage sender"
                                    hasError={errorFor('sender')}
                                    disabled={!canManage}
                                    onAction={() => setOpenSection('sender')}
                                />
                                <CampaignSetupRow
                                    testId="campaign-setup-recipients"
                                    done={recipientsDone}
                                    title="Recipients"
                                    summary={recipientsSummary}
                                    actionLabel={
                                        selectedAudience
                                            ? 'Manage recipients'
                                            : 'Add recipients'
                                    }
                                    hasError={errorFor('recipients')}
                                    disabled={!canManage}
                                    onAction={() =>
                                        setOpenSection('recipients')
                                    }
                                />
                                <CampaignSetupRow
                                    testId="campaign-setup-subject"
                                    done={subjectDone}
                                    title="Subject"
                                    summary={subjectSummary}
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
                                    testId="campaign-setup-design"
                                    done={hasBody}
                                    title="Design"
                                    summary={
                                        hasBody ? undefined : designSummary
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
                                        <CampaignDesignPreview
                                            editor={email.editor}
                                            design={form.data.design}
                                            html={form.data.html}
                                            source={form.data.source}
                                        />
                                    ) : null}
                                </CampaignSetupRow>
                                <CampaignSetupRow
                                    testId="campaign-setup-settings"
                                    done={false}
                                    title="Additional settings"
                                    summary={settingsSummary}
                                    actionLabel="Edit settings"
                                    hasError={errorFor('settings')}
                                    disabled={!canManage}
                                    onAction={() => setOpenSection('settings')}
                                />
                            </div>
                        </div>
                    </div>
                )}
            </form>

            {mediaLibrary ? (
                <MediaLibraryDialog
                    teamSlug={currentTeam.slug}
                    library={mediaLibrary}
                    open={mediaDialogOpen}
                    onOpenChange={setMediaDialogOpen}
                />
            ) : null}

            <Dialog
                open={openSection === 'name'}
                onOpenChange={(open) => setOpenSection(open ? 'name' : null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Campaign name</DialogTitle>
                        <DialogDescription>
                            Only your team sees this.
                        </DialogDescription>
                    </DialogHeader>
                    <Field data-invalid={Boolean(form.errors.name)}>
                        <FieldLabel htmlFor="name">Internal name</FieldLabel>
                        <Input
                            id="name"
                            data-test="email-name"
                            disabled={!canManage}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="March newsletter"
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        <FieldError>{form.errors.name}</FieldError>
                    </Field>
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
                            Use the audience sender by default, or choose a
                            different verified workspace sender for this
                            campaign.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.sender_uuid)}>
                            <FieldLabel htmlFor="sender_uuid">
                                Sender
                            </FieldLabel>
                            <Select
                                items={senderItems}
                                value={form.data.sender_uuid}
                                onValueChange={(value) =>
                                    form.setData(
                                        'sender_uuid',
                                        typeof value === 'string'
                                            ? value
                                            : null,
                                    )
                                }
                                disabled={!canManage}
                            >
                                <SelectTrigger
                                    id="sender_uuid"
                                    className="w-full"
                                    data-test="campaign-sender-select"
                                    aria-invalid={Boolean(
                                        form.errors.sender_uuid,
                                    )}
                                >
                                    <SelectValue placeholder="Select a sender" />
                                </SelectTrigger>
                                <SelectContent alignItemWithTrigger={false}>
                                    <SelectGroup>
                                        {senderItems.map((sender) => (
                                            <SelectItem
                                                key={sender.value}
                                                value={sender.value}
                                            >
                                                {sender.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldDescription>
                                Only verified workspace senders are available.{' '}
                                <Link
                                    href={editWorkspaceSenders(
                                        currentTeam.slug,
                                    )}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    Manage senders
                                </Link>
                                .
                            </FieldDescription>
                            <FieldError>{form.errors.sender_uuid}</FieldError>
                        </Field>
                    </FieldGroup>
                    <DialogFooter>
                        <Button
                            type="button"
                            disabled={
                                form.processing ||
                                form.data.sender_uuid === null
                            }
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
                open={openSection === 'recipients'}
                onOpenChange={(open) =>
                    setOpenSection(open ? 'recipients' : null)
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Recipients</DialogTitle>
                        <DialogDescription>
                            Who this campaign goes to when it is sent.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.audience)}>
                            <FieldLabel htmlFor="audience">Audience</FieldLabel>
                            <Select
                                value={form.data.audience ?? NONE}
                                disabled={!canManage}
                                onValueChange={(value) =>
                                    form.setData({
                                        ...form.data,
                                        audience: value === NONE ? null : value,
                                        segment: null,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="audience"
                                    data-test="email-audience"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={NONE}>
                                            Not set
                                        </SelectItem>
                                        {audiences.map((audience) => (
                                            <SelectItem
                                                key={audience.uuid}
                                                value={audience.uuid}
                                            >
                                                {audience.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>{form.errors.audience}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.segment)}>
                            <FieldLabel htmlFor="segment">Segment</FieldLabel>
                            <Select
                                value={form.data.segment ?? NONE}
                                disabled={
                                    !canManage ||
                                    !selectedAudience ||
                                    selectedAudience.segments.length === 0
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'segment',
                                        value === NONE ? null : value,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="segment"
                                    data-test="email-segment"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={NONE}>
                                            Whole audience
                                        </SelectItem>
                                        {(selectedAudience?.segments ?? []).map(
                                            (segment) => (
                                                <SelectItem
                                                    key={segment.uuid}
                                                    value={segment.uuid}
                                                >
                                                    {segment.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldDescription>
                                Narrows the audience to a saved segment.
                            </FieldDescription>
                            <FieldError>{form.errors.segment}</FieldError>
                        </Field>
                    </FieldGroup>
                    {selectedAudience ? (
                        <p
                            className="text-sm text-muted-foreground"
                            data-test="email-recipient-count"
                        >
                            <span className="font-medium text-foreground">
                                {recipientCount}
                            </span>{' '}
                            subscribed{' '}
                            {recipientCount === 1 ? 'person' : 'people'} in{' '}
                            {selectedSegment?.name ?? selectedAudience.name}.
                        </p>
                    ) : null}
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
                open={openSection === 'subject'}
                onOpenChange={(open) => setOpenSection(open ? 'subject' : null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Subject</DialogTitle>
                        <DialogDescription>
                            How the campaign appears in the inbox.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.subject)}>
                            <FieldLabel htmlFor="subject">Subject</FieldLabel>
                            <Input
                                id="subject"
                                data-test="email-subject"
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
                open={openSection === 'settings'}
                onOpenChange={(open) =>
                    setOpenSection(open ? 'settings' : null)
                }
            >
                <DialogContent className="max-h-[min(40rem,calc(100vh-4rem))] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Additional settings</DialogTitle>
                        <DialogDescription>
                            Plain text, tracking, and attachments.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-6">
                        {email.editor !== 'plain_text' ? (
                            <Field
                                data-invalid={Boolean(form.errors.plain_text)}
                            >
                                <FieldLabel htmlFor="plain_text">
                                    Plain text version
                                </FieldLabel>
                                <Textarea
                                    id="plain_text"
                                    className="min-h-32 font-mono text-xs"
                                    disabled={!canManage}
                                    value={form.data.plain_text}
                                    placeholder={
                                        "Hello {{ name }},\n\nRead this month's update…"
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.plain_text,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'plain_text',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldDescription>
                                    Used by inboxes that prefer text-only mail.
                                    Leave blank to generate it from the HTML.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.plain_text}
                                </FieldError>
                            </Field>
                        ) : null}

                        <FieldGroup>
                            <Field
                                data-invalid={Boolean(form.errors.query_string)}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <FieldLabel htmlFor="query_string">
                                        Query string
                                    </FieldLabel>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            !canManage ||
                                            form.isDirty ||
                                            linkCheck.processing
                                        }
                                        data-test="check-email-links"
                                        onClick={runLinkCheck}
                                    >
                                        {linkCheck.processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <HugeiconsIcon
                                                icon={Link01Icon}
                                                data-icon="inline-start"
                                            />
                                        )}
                                        Check links
                                    </Button>
                                </div>
                                <Input
                                    id="query_string"
                                    disabled={!canManage}
                                    value={form.data.query_string}
                                    placeholder="utm_source=newsletter&utm_campaign=launch"
                                    aria-invalid={Boolean(
                                        form.errors.query_string,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'query_string',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldDescription>
                                    Appended to every HTTP or HTTPS link. A
                                    leading ? is optional.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.query_string}
                                </FieldError>
                            </Field>

                            {form.isDirty ? (
                                <p className="text-xs text-muted-foreground">
                                    Save changes before checking links.
                                </p>
                            ) : null}

                            {linkCheckResult && !form.isDirty ? (
                                <Alert
                                    variant={
                                        linkCheckResult.broken.length > 0
                                            ? 'destructive'
                                            : 'default'
                                    }
                                >
                                    <HugeiconsIcon
                                        icon={
                                            linkCheckResult.broken.length > 0
                                                ? Alert01Icon
                                                : CheckmarkCircle02Icon
                                        }
                                    />
                                    <AlertTitle>
                                        {linkCheckResult.broken.length > 0
                                            ? `${linkCheckResult.broken.length} broken or unreachable link${linkCheckResult.broken.length === 1 ? '' : 's'}`
                                            : `${linkCheckResult.checked} link${linkCheckResult.checked === 1 ? '' : 's'} checked`}
                                    </AlertTitle>
                                    <AlertDescription>
                                        {linkCheckResult.broken.length > 0 ? (
                                            <ul className="flex flex-col gap-1">
                                                {linkCheckResult.broken.map(
                                                    (result) => (
                                                        <li
                                                            key={result.url}
                                                            className="break-all"
                                                        >
                                                            {result.url} —{' '}
                                                            {result.reason}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        ) : (
                                            'No broken links were found.'
                                        )}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <Field
                                orientation="horizontal"
                                data-disabled={!canManage}
                            >
                                <FieldContent>
                                    <FieldLabel htmlFor="track_clicks">
                                        Track clicks
                                    </FieldLabel>
                                    <FieldDescription>
                                        Route links through signed campaign
                                        redirects.
                                    </FieldDescription>
                                </FieldContent>
                                <Switch
                                    id="track_clicks"
                                    checked={form.data.track_clicks}
                                    disabled={!canManage}
                                    onCheckedChange={(checked) =>
                                        form.setData('track_clicks', checked)
                                    }
                                />
                            </Field>
                            <Field
                                orientation="horizontal"
                                data-disabled={!canManage}
                            >
                                <FieldContent>
                                    <FieldLabel htmlFor="track_opens">
                                        Track opens
                                    </FieldLabel>
                                    <FieldDescription>
                                        Add a private one-pixel open signal to
                                        each delivery.
                                    </FieldDescription>
                                </FieldContent>
                                <Switch
                                    id="track_opens"
                                    checked={form.data.track_opens}
                                    disabled={!canManage}
                                    onCheckedChange={(checked) =>
                                        form.setData('track_opens', checked)
                                    }
                                />
                            </Field>
                        </FieldGroup>

                        <div className="flex flex-col gap-4">
                            <div>
                                <p className="font-medium">Attachments</p>
                                <p className="text-sm text-muted-foreground">
                                    Add up to 10 JPEG, JPG, GIF, PNG, PDF, or
                                    ZIP files. Each file may be up to 10 MB.
                                </p>
                            </div>
                            {email.attachments.length > 0 ? (
                                <ul className="flex flex-col gap-2">
                                    {email.attachments.map((attachment) => (
                                        <li
                                            key={attachment.uuid}
                                            className="flex items-center gap-3 rounded-lg border p-3"
                                        >
                                            <HugeiconsIcon
                                                icon={Attachment01Icon}
                                                className="size-5 shrink-0 text-muted-foreground"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {attachment.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attachment.size_label}
                                                </p>
                                            </div>
                                            {canManage ? (
                                                <Button
                                                    type="button"
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    aria-label={`Remove ${attachment.name}`}
                                                    onClick={() =>
                                                        removeAttachment(
                                                            attachment.uuid,
                                                        )
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={Delete02Icon}
                                                    />
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            {canManage && email.attachments.length < 10 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="self-start"
                                    disabled={uploadingAttachments}
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                >
                                    {uploadingAttachments ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <HugeiconsIcon
                                            icon={Upload01Icon}
                                            data-icon="inline-start"
                                        />
                                    )}
                                    Add attachments
                                </Button>
                            ) : null}
                            <FieldError>{form.errors.attachments}</FieldError>
                        </div>
                    </div>
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
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept={ATTACHMENT_ACCEPT}
                        multiple
                        className="sr-only"
                        data-test="email-attachment-input"
                        onChange={(event) => {
                            if (event.target.files) {
                                uploadAttachments(event.target.files);
                            }
                        }}
                    />

                    {templateBody && (
                        <SaveEmailAsTemplateDialog
                            teamSlug={currentTeam.slug}
                            defaultName={form.data.name}
                            subject={templateBody.subject}
                            preheader={templateBody.preheader}
                            html={templateBody.html}
                            source={templateBody.source}
                            design={templateBody.design}
                            open
                            onOpenChange={(open) =>
                                setTemplateBody(open ? templateBody : null)
                            }
                        />
                    )}

                    <DeleteEmailModal
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
