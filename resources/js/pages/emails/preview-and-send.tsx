import {
    ArrowLeft01Icon,
    ArrowRight01Icon,
    InformationCircleIcon,
    Link01Icon,
    MailRemove01Icon,
    MailSend01Icon,
    UserAdd01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Deferred,
    Head,
    Link,
    router,
    setLayoutProps,
    useHttp,
    usePage,
} from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { SyntheticEvent } from 'react';
import { LastTestStatus } from '@/components/last-test-status';
import PreviewWidthTabs from '@/components/preview-width-tabs';
import type { PreviewWidth } from '@/components/preview-width-tabs';
import SendTestEmailDialog from '@/components/send-test-email-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverDescription,
    PopoverHeader,
    PopoverTitle,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import {
    checkLinks,
    composePreview,
    edit as editCampaign,
    send,
} from '@/routes/emails';
import type { LastTestSend, SendReadiness, SesAccountLimits } from '@/types';

type PreviewRecipient = {
    uuid: string;
    name: string;
    email: string;
    label: string;
};

type CampaignPreview = {
    recipient: PreviewRecipient;
    recipients: PreviewRecipient[];
    navigation: {
        previous: string | null;
        next: string | null;
        position: number;
    };
    subject: string;
    preheader: string | null;
    html: string;
};

type Props = {
    campaign: {
        uuid: string;
        name: string;
        subject: string;
        from_name: string;
        from_address: string;
        last_test: LastTestSend | null;
    };
    recipientCount: number;
    sendReadiness: SendReadiness;
    /** Deferred: the workspace's SES limits, null for other providers. */
    sesQuota?: SesAccountLimits | null;
    suppressedRecipients: SuppressedRecipients;
    unconfirmedRecipients: number;
    missingUnsubscribe: boolean;
    contentIssues: ContentIssue[];
    preview: CampaignPreview;
};

type ContentIssue = {
    level: 'warning' | 'notice';
    code: string;
    message: string;
};

type BrokenLink = { url: string; status: number | null; reason: string };

type LinkCheckResponse = { checked: number; broken: BrokenLink[] };

type SuppressedRecipients = {
    count: number;
    reasons: { label: string; count: number }[];
};

const ZOOM_LEVELS = ['75', '100', '125'] as const;
type ZoomLevel = (typeof ZOOM_LEVELS)[number];

const PREVIEW_UNSUBSCRIBE_HREF = '#unsubscribe';
const PREVIEW_WEB_VIEW_HREF = '#web-view';
const PREVIEW_SUBSCRIBE_HREF = '#subscribe';

type PreviewLinkKind = 'unsubscribe' | 'web-view' | 'subscribe' | 'http';

type PreviewLink = {
    href: string;
    label: string;
    kind: PreviewLinkKind;
};

function classifyPreviewHref(href: string): PreviewLinkKind | null {
    const trimmed = href.trim();

    if (
        trimmed === PREVIEW_UNSUBSCRIBE_HREF ||
        trimmed.endsWith(PREVIEW_UNSUBSCRIBE_HREF)
    ) {
        return 'unsubscribe';
    }

    if (
        trimmed === PREVIEW_WEB_VIEW_HREF ||
        trimmed.endsWith(PREVIEW_WEB_VIEW_HREF)
    ) {
        return 'web-view';
    }

    if (
        trimmed === PREVIEW_SUBSCRIBE_HREF ||
        trimmed.endsWith(PREVIEW_SUBSCRIBE_HREF)
    ) {
        return 'subscribe';
    }

    if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith('mailto:')) {
        return 'http';
    }

    return null;
}

function previewLinkKindLabel(kind: PreviewLinkKind): string {
    if (kind === 'unsubscribe') {
        return 'Unsubscribe';
    }

    if (kind === 'web-view') {
        return 'View in browser';
    }

    if (kind === 'subscribe') {
        return 'Subscribe';
    }

    return 'Link';
}

function collectPreviewLinks(doc: Document): PreviewLink[] {
    const links: PreviewLink[] = [];
    const seen = new Set<string>();

    doc.querySelectorAll('a[href]').forEach((node) => {
        const href = node.getAttribute('href')?.trim() ?? '';
        const kind = classifyPreviewHref(href);

        if (kind === null || seen.has(href)) {
            return;
        }

        seen.add(href);
        links.push({
            href,
            kind,
            label: node.textContent?.replace(/\s+/g, ' ').trim() || href,
        });
    });

    return links;
}

function findPreviewAnchor(
    frame: HTMLIFrameElement,
    clientX: number,
    clientY: number,
    scale: number,
): HTMLAnchorElement | null {
    const doc = frame.contentDocument;
    const wrapper = frame.parentElement;

    if (!doc?.defaultView || !wrapper) {
        return null;
    }

    const rect = wrapper.getBoundingClientRect();
    const x = (clientX - rect.left) / scale;
    const y = (clientY - rect.top) / scale;
    let node: Element | null = doc.elementFromPoint(x, y);

    while (node && node !== doc.documentElement) {
        if (node instanceof doc.defaultView.HTMLAnchorElement) {
            return node;
        }

        node = node.parentElement;
    }

    return null;
}

function MissingUnsubscribeCallout({ className }: { className?: string }) {
    return (
        <Alert
            className={className}
            data-test="campaign-preview-missing-unsubscribe"
        >
            <HugeiconsIcon icon={InformationCircleIcon} />
            <AlertTitle>Maildun will add an unsubscribe footer</AlertTitle>
            <AlertDescription>
                The body has no{' '}
                <code className="font-mono">{'{{ unsubscribe_url }}'}</code>{' '}
                link, so every recipient still gets one in a standard footer.
                Placing the link in your own design usually looks better and can
                help inbox placement.
            </AlertDescription>
        </Alert>
    );
}

function SuppressedRecipientsCallout({
    suppressed,
}: {
    suppressed: SuppressedRecipients;
}) {
    return (
        <Alert
            className="mx-auto w-full max-w-3xl shrink-0"
            data-test="campaign-preview-suppressed"
        >
            <HugeiconsIcon icon={InformationCircleIcon} />
            <AlertTitle>
                Skipping {suppressed.count} suppressed{' '}
                {suppressed.count === 1 ? 'recipient' : 'recipients'}
            </AlertTitle>
            <AlertDescription>
                <p>
                    {suppressed.count === 1
                        ? "This address bounced permanently or reported spam before, so Maildun won't email it again."
                        : "These addresses bounced permanently or reported spam before, so Maildun won't email them again."}
                </p>
                <p className="tabular-nums">
                    {suppressed.reasons
                        .map((reason) => `${reason.label}: ${reason.count}`)
                        .join(' · ')}
                </p>
            </AlertDescription>
        </Alert>
    );
}

/**
 * Pre-send content checks from LintCampaignContent. None of them block the
 * send; warnings come first because they are likely to hurt delivery.
 */
function ContentIssuesCallout({ issues }: { issues: ContentIssue[] }) {
    const hasWarning = issues.some((issue) => issue.level === 'warning');
    const sorted = [...issues].sort(
        (first, second) =>
            Number(second.level === 'warning') -
            Number(first.level === 'warning'),
    );

    return (
        <Alert
            variant={hasWarning ? 'warning' : 'default'}
            className="mx-auto w-full max-w-3xl shrink-0"
            data-test="campaign-preview-content-issues"
        >
            <HugeiconsIcon icon={InformationCircleIcon} />
            <AlertTitle>
                {issues.length === 1
                    ? '1 thing to check before sending'
                    : `${issues.length} things to check before sending`}
            </AlertTitle>
            <AlertDescription>
                <ul className="flex list-disc flex-col gap-1 pl-4">
                    {sorted.map((issue) => (
                        <li key={issue.code}>{issue.message}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}

/**
 * SES refuses messages past the account's 24-hour quota, and a sandbox
 * account only delivers to verified addresses, so say so before sending.
 */
function SesQuotaWarning({
    quota,
    recipientCount,
}: {
    quota: SesAccountLimits | null;
    recipientCount: number;
}) {
    if (quota === null || !quota.available) {
        return null;
    }

    if (quota.sandbox) {
        return (
            <p
                className="text-sm text-warning"
                data-test="confirm-send-ses-sandbox"
            >
                Your Amazon SES account is in the sandbox, so it only delivers
                to verified addresses. Request production access first.
            </p>
        );
    }

    if (recipientCount <= quota.remaining) {
        return null;
    }

    return (
        <p className="text-sm text-warning" data-test="confirm-send-ses-quota">
            Amazon SES allows {quota.remaining.toLocaleString()} more{' '}
            {quota.remaining === 1 ? 'message' : 'messages'} in the next 24
            hours. The other{' '}
            {(recipientCount - quota.remaining).toLocaleString()} will fail
            until the quota frees up; you can retry them afterwards.
        </p>
    );
}

function BrokenLinksCallout({ links }: { links: BrokenLink[] }) {
    return (
        <Alert
            variant="destructive"
            className="mx-auto w-full max-w-3xl shrink-0"
            data-test="campaign-preview-broken-links"
        >
            <HugeiconsIcon icon={InformationCircleIcon} />
            <AlertTitle>
                {links.length === 1
                    ? '1 link looks broken'
                    : `${links.length} links look broken`}
            </AlertTitle>
            <AlertDescription>
                <ul className="flex flex-col gap-1">
                    {links.map((link) => (
                        <li key={link.url} className="break-all">
                            <span className="font-medium">{link.url}</span>
                            {' — '}
                            {link.reason}
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}

function UnconfirmedRecipientsCallout({ count }: { count: number }) {
    return (
        <Alert
            className="mx-auto w-full max-w-3xl shrink-0"
            data-test="campaign-preview-unconfirmed"
        >
            <HugeiconsIcon icon={InformationCircleIcon} />
            <AlertTitle>
                Skipping {count} unconfirmed{' '}
                {count === 1 ? 'recipient' : 'recipients'}
            </AlertTitle>
            <AlertDescription>
                {count === 1
                    ? "This person signed up but hasn't clicked the double opt-in confirmation link yet."
                    : "These people signed up but haven't clicked the double opt-in confirmation link yet."}
            </AlertDescription>
        </Alert>
    );
}

function PreviewLinksList({
    links,
    onOpen,
}: {
    links: PreviewLink[];
    onOpen: (href: string) => void;
}) {
    if (links.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No clickable links in this preview.
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-1">
            {links.map((link) => (
                <li key={link.href}>
                    <button
                        type="button"
                        className="flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-2 text-left text-sm hover:bg-muted"
                        data-test="campaign-preview-link"
                        onClick={() => onOpen(link.href)}
                    >
                        <span className="font-medium">{link.label}</span>
                        <span className="text-xs break-all text-muted-foreground">
                            {previewLinkKindLabel(link.kind)}
                            {link.kind === 'http' ? ` · ${link.href}` : ''}
                        </span>
                    </button>
                </li>
            ))}
        </ul>
    );
}

function measurePreviewDocumentHeight(frame: HTMLIFrameElement): number {
    const doc = frame.contentDocument;

    if (!doc?.documentElement) {
        return 0;
    }

    const html = doc.documentElement;
    const body = doc.body;

    html.style.height = 'auto';
    html.style.minHeight = '0';
    html.style.overflow = 'visible';

    if (body) {
        body.style.height = 'auto';
        body.style.minHeight = '0';
        body.style.overflow = 'visible';
    }

    return Math.max(
        html.scrollHeight,
        html.offsetHeight,
        body?.scrollHeight ?? 0,
        body?.offsetHeight ?? 0,
    );
}

export default function PreviewAndSend({
    campaign,
    recipientCount,
    suppressedRecipients,
    unconfirmedRecipients,
    contentIssues,
    missingUnsubscribe,
    sendReadiness,
    sesQuota,
    preview: initialPreview,
}: Props) {
    const { auth, currentTeam } = usePage().props;
    const [preview, setPreview] = useState(initialPreview);
    const [previewWidth, setPreviewWidth] = useState<PreviewWidth>('desktop');
    const [zoom, setZoom] = useState<ZoomLevel>('100');
    const [inputValue, setInputValue] = useState(
        initialPreview.recipient.label,
    );
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [sendError, setSendError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [confirmSendOpen, setConfirmSendOpen] = useState(false);
    const [testOpen, setTestOpen] = useState(false);
    const [previewDocumentHeight, setPreviewDocumentHeight] = useState(0);
    const [previewLinks, setPreviewLinks] = useState<PreviewLink[]>([]);
    const [hoveringLink, setHoveringLink] = useState(false);
    const [openedLink, setOpenedLink] = useState<PreviewLinkKind | null>(null);
    const [linksSheetOpen, setLinksSheetOpen] = useState(false);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestRequest = useRef(0);
    const previewHeightObserver = useRef<ResizeObserver | null>(null);
    const canvasRef = useRef<HTMLDivElement>(null);
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const previewRequest = useHttp<Record<string, never>, CampaignPreview>({});
    const linkCheck = useHttp<Record<string, never>, LinkCheckResponse>({});
    const linkCheckStarted = useRef(false);
    const [brokenLinks, setBrokenLinks] = useState<BrokenLink[]>([]);

    setLayoutProps({ fullscreen: true });

    useEffect(() => {
        return () => {
            previewHeightObserver.current?.disconnect();
        };
    }, []);

    // Check every tracked link once when the page opens, following
    // redirects, so a broken destination is caught before the send.
    useEffect(() => {
        if (!currentTeam || linkCheckStarted.current) {
            return;
        }

        linkCheckStarted.current = true;
        void linkCheck
            .get(checkLinks.url([currentTeam.slug, campaign.uuid]), {
                onSuccess: (response) => setBrokenLinks(response.broken),
            })
            .catch(() => undefined);
    }, [currentTeam, campaign.uuid, linkCheck]);

    if (!currentTeam) {
        return null;
    }

    const clearSearchTimer = () => {
        if (searchTimer.current) {
            clearTimeout(searchTimer.current);
            searchTimer.current = null;
        }
    };

    const loadPreview = (
        recipientUuid: string,
        query = '',
        preserveInput = false,
    ) => {
        const requestId = latestRequest.current + 1;
        latestRequest.current = requestId;
        setPreviewError(null);

        void previewRequest
            .get(
                composePreview.url([currentTeam.slug, campaign.uuid], {
                    query: {
                        recipient: recipientUuid,
                        q: query || undefined,
                    },
                }),
                {
                    onSuccess: (response) => {
                        if (latestRequest.current !== requestId) {
                            return;
                        }

                        setPreview(response);

                        if (!preserveInput) {
                            setInputValue(response.recipient.label);
                        }
                    },
                    onError: (errors) => {
                        if (latestRequest.current !== requestId) {
                            return;
                        }

                        setPreviewError(
                            typeof errors.email === 'string'
                                ? errors.email
                                : 'Unable to load the campaign preview.',
                        );
                    },
                    onHttpException: () => {
                        if (latestRequest.current === requestId) {
                            setPreviewError(
                                'Unable to load the campaign preview.',
                            );
                        }
                    },
                    onNetworkError: () => {
                        if (latestRequest.current === requestId) {
                            setPreviewError(
                                'Unable to load the campaign preview.',
                            );
                        }
                    },
                },
            )
            .catch(() => {});
    };

    const handleInputValueChange = (value: string) => {
        setInputValue(value);
        clearSearchTimer();

        if (value === preview.recipient.label) {
            return;
        }

        searchTimer.current = setTimeout(() => {
            loadPreview(preview.recipient.uuid, value, true);
        }, 300);
    };

    const handleRecipientChange = (label: string | null) => {
        if (!label) {
            return;
        }

        const recipient = preview.recipients.find(
            (option) => option.label === label,
        );

        if (!recipient) {
            return;
        }

        clearSearchTimer();
        setInputValue(recipient.label);
        loadPreview(recipient.uuid);
    };

    const syncPreviewDocumentHeight = (frame: HTMLIFrameElement) => {
        const height = measurePreviewDocumentHeight(frame);

        if (height > 0) {
            setPreviewDocumentHeight((current) =>
                current === height ? current : height,
            );
        }
    };

    const handlePreviewLoad = (event: SyntheticEvent<HTMLIFrameElement>) => {
        const frame = event.currentTarget;
        const doc = frame.contentDocument;

        previewHeightObserver.current?.disconnect();
        syncPreviewDocumentHeight(frame);
        setPreviewLinks(doc ? collectPreviewLinks(doc) : []);
        setHoveringLink(false);

        if (!doc?.documentElement) {
            return;
        }

        const observer = new ResizeObserver(() => {
            syncPreviewDocumentHeight(frame);
        });

        observer.observe(doc.documentElement);

        if (doc.body) {
            observer.observe(doc.body);
        }

        previewHeightObserver.current = observer;
    };

    const openPreviewLink = (href: string) => {
        const kind = classifyPreviewHref(href);

        if (
            kind === 'unsubscribe' ||
            kind === 'web-view' ||
            kind === 'subscribe'
        ) {
            setOpenedLink(kind);

            return;
        }

        if (kind === 'http') {
            window.open(href, '_blank', 'noopener,noreferrer');
        }
    };

    const previewAnchorFromPointer = (
        clientX: number,
        clientY: number,
    ): HTMLAnchorElement | null => {
        const frame = iframeRef.current;

        if (!frame) {
            return null;
        }

        return findPreviewAnchor(frame, clientX, clientY, previewScale);
    };

    const handleSend = () => {
        setSendError(null);

        router.post(
            send.url([currentTeam.slug, campaign.uuid]),
            {},
            {
                onStart: () => setSending(true),
                onError: (errors) =>
                    setSendError(
                        typeof errors.email === 'string'
                            ? errors.email
                            : 'Unable to queue this campaign.',
                    ),
                onFinish: () => {
                    setSending(false);
                    setConfirmSendOpen(false);
                },
            },
        );
    };
    const failedReadiness = sendReadiness.checks.filter(
        (check) => !check.passed,
    );

    const recipientLabels = preview.recipients.map(
        (recipient) => recipient.label,
    );
    const previewFrameWidth = previewWidth === 'desktop' ? 600 : 375;
    const previewScale = Number(zoom) / 100;
    const scaledPreviewHeight =
        previewDocumentHeight > 0
            ? previewDocumentHeight * previewScale
            : undefined;

    return (
        <>
            <Head title={`Preview and Send ${campaign.name}`} />

            <div
                className="flex h-dvh min-h-0 w-full flex-col overflow-hidden bg-background"
                data-test="preview-and-send-page"
            >
                <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b px-3 sm:px-4">
                    <div className="flex min-w-0 items-center gap-2">
                        <Link
                            href={editCampaign([
                                currentTeam.slug,
                                campaign.uuid,
                            ])}
                            aria-label="Back to campaign setup"
                            className={cn(
                                buttonVariants({
                                    variant: 'ghost',
                                    size: 'icon-sm',
                                }),
                                'shrink-0',
                            )}
                            data-test="preview-and-send-back"
                        >
                            <HugeiconsIcon icon={ArrowLeft01Icon} />
                        </Link>
                        <Separator orientation="vertical" className="h-5" />
                        <span className="max-w-40 truncate font-medium sm:max-w-72 lg:max-w-lg">
                            {campaign.name}
                        </span>
                        <Badge variant="secondary">Draft</Badge>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <LastTestStatus
                            test={campaign.last_test}
                            pollProp="campaign"
                            className="hidden max-w-72 truncate lg:block"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            disabled={sending}
                            onClick={() => setTestOpen(true)}
                            data-test="send-test-button"
                        >
                            Send test
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                sending ||
                                previewRequest.processing ||
                                Boolean(previewError)
                            }
                            onClick={() => setConfirmSendOpen(true)}
                            data-test="send-campaign-button"
                        >
                            {sending ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <HugeiconsIcon
                                    icon={MailSend01Icon}
                                    data-icon="inline-start"
                                />
                            )}
                            {sending
                                ? 'Queueing campaign…'
                                : `Send to ${recipientCount} ${recipientCount === 1 ? 'recipient' : 'recipients'}`}
                        </Button>
                    </div>
                </header>

                <div
                    className="flex h-14 shrink-0 items-center gap-3 overflow-x-auto overflow-y-hidden border-b px-3 sm:px-4"
                    data-test="campaign-preview-toolbar"
                >
                    <div className="flex shrink-0 items-center gap-3">
                        <PreviewWidthTabs
                            value={previewWidth}
                            onValueChange={setPreviewWidth}
                            testIdPrefix="campaign-preview-width"
                        />
                        <Select
                            value={zoom}
                            onValueChange={(value) => {
                                if (
                                    typeof value === 'string' &&
                                    ZOOM_LEVELS.includes(value as ZoomLevel)
                                ) {
                                    setZoom(value as ZoomLevel);
                                }
                            }}
                        >
                            <SelectTrigger
                                className="w-24"
                                aria-label="Preview zoom"
                                data-test="campaign-preview-zoom"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent alignItemWithTrigger={false}>
                                <SelectGroup>
                                    {ZOOM_LEVELS.map((level) => (
                                        <SelectItem key={level} value={level}>
                                            {level}%
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="ml-auto flex min-w-0 shrink-0 items-center gap-3">
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {preview.navigation.position} of {recipientCount}
                        </span>
                        <Sheet
                            open={linksSheetOpen}
                            onOpenChange={setLinksSheetOpen}
                        >
                            <SheetTrigger
                                render={
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="lg:hidden"
                                        aria-label="Check preview links"
                                        data-test="campaign-preview-open-links"
                                    />
                                }
                            >
                                <HugeiconsIcon
                                    icon={Link01Icon}
                                    data-icon="inline-start"
                                />
                                {previewLinks.length}{' '}
                                {previewLinks.length === 1 ? 'link' : 'links'}
                            </SheetTrigger>
                            <SheetContent side="right" className="w-80">
                                <SheetHeader>
                                    <SheetTitle>Links</SheetTitle>
                                    <SheetDescription>
                                        Click a button or link in the preview,
                                        or open it from this list. Unsubscribe
                                        and view-in-browser stay in this page so
                                        nobody is opted out.
                                    </SheetDescription>
                                </SheetHeader>
                                <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-4">
                                    <div className="flex flex-col gap-4">
                                        {missingUnsubscribe ? (
                                            <MissingUnsubscribeCallout />
                                        ) : null}
                                        <PreviewLinksList
                                            links={previewLinks}
                                            onOpen={(href) => {
                                                setLinksSheetOpen(false);
                                                openPreviewLink(href);
                                            }}
                                        />
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                        <Popover>
                            <PopoverTrigger
                                render={
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Preview details"
                                    />
                                }
                            >
                                <HugeiconsIcon icon={InformationCircleIcon} />
                            </PopoverTrigger>
                            <PopoverContent align="end" className="w-80">
                                <PopoverHeader>
                                    <PopoverTitle>Preview details</PopoverTitle>
                                    <PopoverDescription>
                                        Merge tags are rendered with the
                                        selected recipient&apos;s saved data.
                                    </PopoverDescription>
                                </PopoverHeader>
                                <div className="flex flex-col gap-3">
                                    <div className="flex flex-col gap-1">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Subject
                                        </p>
                                        <p className="break-words">
                                            {preview.subject}
                                        </p>
                                    </div>
                                    {preview.preheader ? (
                                        <div className="flex flex-col gap-1">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Preheader
                                            </p>
                                            <p className="break-words">
                                                {preview.preheader}
                                            </p>
                                        </div>
                                    ) : null}
                                </div>
                            </PopoverContent>
                        </Popover>

                        <ButtonGroup aria-label="Preview recipient navigation">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Previous recipient"
                                disabled={
                                    previewRequest.processing ||
                                    !preview.navigation.previous
                                }
                                onClick={() => {
                                    if (preview.navigation.previous) {
                                        clearSearchTimer();
                                        loadPreview(
                                            preview.navigation.previous,
                                        );
                                    }
                                }}
                                data-test="campaign-preview-previous-recipient"
                            >
                                <HugeiconsIcon icon={ArrowLeft01Icon} />
                            </Button>
                            <Combobox
                                autoHighlight
                                items={recipientLabels}
                                filter={null}
                                value={preview.recipient.label}
                                inputValue={inputValue}
                                onInputValueChange={handleInputValueChange}
                                onValueChange={handleRecipientChange}
                            >
                                <ComboboxInput
                                    className="w-80 lg:w-96"
                                    placeholder="Search recipients"
                                    aria-label="Preview as recipient"
                                    data-test="campaign-preview-recipient"
                                />
                                <ComboboxContent align="end" className="w-96">
                                    <ComboboxEmpty>
                                        {previewRequest.processing
                                            ? 'Searching recipients…'
                                            : 'No recipients found.'}
                                    </ComboboxEmpty>
                                    <ComboboxList>
                                        {(label: string) => {
                                            const recipient =
                                                preview.recipients.find(
                                                    (option) =>
                                                        option.label === label,
                                                );

                                            return (
                                                <ComboboxItem
                                                    key={label}
                                                    value={label}
                                                    className="items-start py-1.5"
                                                >
                                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5 pr-2">
                                                        <span className="leading-5">
                                                            {recipient?.name}
                                                        </span>
                                                        <span className="text-xs leading-4 break-all text-muted-foreground">
                                                            {recipient?.email}
                                                        </span>
                                                    </span>
                                                </ComboboxItem>
                                            );
                                        }}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Next recipient"
                                disabled={
                                    previewRequest.processing ||
                                    !preview.navigation.next
                                }
                                onClick={() => {
                                    if (preview.navigation.next) {
                                        clearSearchTimer();
                                        loadPreview(preview.navigation.next);
                                    }
                                }}
                                data-test="campaign-preview-next-recipient"
                            >
                                <HugeiconsIcon icon={ArrowRight01Icon} />
                            </Button>
                        </ButtonGroup>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1 overflow-hidden">
                    <main className="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-hidden bg-muted/40 p-4 sm:p-8">
                        {missingUnsubscribe ? (
                            <MissingUnsubscribeCallout className="shrink-0 lg:hidden" />
                        ) : null}
                        {suppressedRecipients.count > 0 ? (
                            <SuppressedRecipientsCallout
                                suppressed={suppressedRecipients}
                            />
                        ) : null}
                        {brokenLinks.length > 0 ? (
                            <BrokenLinksCallout links={brokenLinks} />
                        ) : null}
                        {contentIssues.length > 0 ? (
                            <ContentIssuesCallout issues={contentIssues} />
                        ) : null}
                        {unconfirmedRecipients > 0 ? (
                            <UnconfirmedRecipientsCallout
                                count={unconfirmedRecipients}
                            />
                        ) : null}
                        {previewError || sendError ? (
                            <div className="mx-auto flex w-full max-w-3xl shrink-0 flex-col gap-3">
                                {previewError ? (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            Preview unavailable
                                        </AlertTitle>
                                        <AlertDescription>
                                            {previewError}
                                        </AlertDescription>
                                    </Alert>
                                ) : null}
                                {sendError ? (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            Campaign not queued
                                        </AlertTitle>
                                        <AlertDescription>
                                            {sendError}
                                        </AlertDescription>
                                    </Alert>
                                ) : null}
                            </div>
                        ) : null}

                        <div
                            ref={canvasRef}
                            className="mx-auto min-h-0 w-fit max-w-full flex-1 overflow-auto rounded-lg border bg-background shadow-sm"
                            data-test="campaign-preview-canvas"
                        >
                            <div
                                className="relative overflow-hidden"
                                style={{
                                    width: previewFrameWidth * previewScale,
                                    height: scaledPreviewHeight,
                                }}
                            >
                                <iframe
                                    ref={iframeRef}
                                    key={`${preview.recipient.uuid}-${previewWidth}`}
                                    title={`Campaign preview for ${preview.recipient.email}`}
                                    srcDoc={preview.html}
                                    sandbox="allow-same-origin"
                                    scrolling="no"
                                    onLoad={handlePreviewLoad}
                                    className="pointer-events-none block origin-top-left overflow-hidden border-0 bg-background"
                                    style={{
                                        width: previewFrameWidth,
                                        height:
                                            previewDocumentHeight > 0
                                                ? previewDocumentHeight
                                                : 'auto',
                                        transform: `scale(${previewScale})`,
                                    }}
                                    data-test="campaign-recipient-preview"
                                />
                                <div
                                    className="absolute inset-0"
                                    data-test="campaign-preview-hit-layer"
                                    style={{
                                        cursor: hoveringLink
                                            ? 'pointer'
                                            : 'default',
                                    }}
                                    onWheel={(event) => {
                                        event.preventDefault();
                                        canvasRef.current?.scrollBy({
                                            top: event.deltaY,
                                            left: event.deltaX,
                                        });
                                    }}
                                    onMouseMove={(event) => {
                                        setHoveringLink(
                                            Boolean(
                                                previewAnchorFromPointer(
                                                    event.clientX,
                                                    event.clientY,
                                                ),
                                            ),
                                        );
                                    }}
                                    onMouseLeave={() => setHoveringLink(false)}
                                    onClick={(event) => {
                                        const anchor = previewAnchorFromPointer(
                                            event.clientX,
                                            event.clientY,
                                        );
                                        const href =
                                            anchor?.getAttribute('href');

                                        if (href) {
                                            openPreviewLink(href);
                                        }
                                    }}
                                />
                            </div>
                        </div>
                    </main>
                    <aside
                        className="hidden min-h-0 w-80 shrink-0 flex-col border-l bg-background lg:flex"
                        data-test="campaign-preview-links"
                    >
                        <div className="flex flex-col gap-1.5 border-b px-4 py-3">
                            <p className="font-heading font-medium">Links</p>
                            <p className="text-sm text-muted-foreground">
                                Click a button or link in the preview, or open
                                it from this list. Unsubscribe and
                                view-in-browser stay in this page so nobody is
                                opted out.
                            </p>
                        </div>
                        <div className="min-h-0 flex-1 overflow-y-auto p-4">
                            <div className="flex flex-col gap-4">
                                {missingUnsubscribe ? (
                                    <MissingUnsubscribeCallout />
                                ) : null}
                                <PreviewLinksList
                                    links={previewLinks}
                                    onOpen={openPreviewLink}
                                />
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <AlertDialog
                open={confirmSendOpen}
                onOpenChange={(open) => !sending && setConfirmSendOpen(open)}
            >
                <AlertDialogContent data-test="confirm-send-dialog">
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {failedReadiness.length > 0
                                ? 'This campaign cannot be sent yet'
                                : `Send to ${recipientCount.toLocaleString()} ${recipientCount === 1 ? 'recipient' : 'recipients'}?`}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {failedReadiness.length > 0
                                ? 'Fix these first, then come back to send.'
                                : 'Sending cannot be undone. Recipients who were skipped as suppressed or unconfirmed are not included.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {failedReadiness.length > 0 ? (
                        <ul
                            className="flex list-disc flex-col gap-1 pl-5 text-sm"
                            data-test="confirm-send-blockers"
                        >
                            {failedReadiness.map((check) => (
                                <li key={check.key}>
                                    {check.message}{' '}
                                    {check.action_url ? (
                                        <a
                                            href={check.action_url}
                                            className="font-medium underline underline-offset-4"
                                        >
                                            Fix
                                        </a>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                            <dt className="text-muted-foreground">From</dt>
                            <dd className="min-w-0 truncate">
                                {campaign.from_name
                                    ? `${campaign.from_name} <${campaign.from_address}>`
                                    : campaign.from_address}
                            </dd>
                            <dt className="text-muted-foreground">Subject</dt>
                            <dd className="min-w-0 truncate">
                                {campaign.subject}
                            </dd>
                            <dt className="text-muted-foreground">
                                Recipients
                            </dt>
                            <dd className="tabular-nums">
                                {recipientCount.toLocaleString()}
                            </dd>
                        </dl>
                    )}
                    {failedReadiness.length === 0 ? (
                        <Deferred data="sesQuota" fallback={null}>
                            <SesQuotaWarning
                                quota={sesQuota ?? null}
                                recipientCount={recipientCount}
                            />
                        </Deferred>
                    ) : null}
                    {sendError ? (
                        <p className="text-sm text-destructive">{sendError}</p>
                    ) : null}
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={sending}>
                            Cancel
                        </AlertDialogCancel>
                        {failedReadiness.length === 0 ? (
                            <AlertDialogAction
                                data-test="confirm-send-campaign"
                                disabled={sending}
                                onClick={handleSend}
                            >
                                {sending && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Send campaign
                            </AlertDialogAction>
                        ) : null}
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <SendTestEmailDialog
                teamSlug={currentTeam.slug}
                emailUuid={campaign.uuid}
                defaultAddress={auth.user.email}
                open={testOpen}
                onOpenChange={setTestOpen}
            />

            <Dialog
                open={openedLink === 'unsubscribe'}
                onOpenChange={(open) => {
                    if (!open) {
                        setOpenedLink(null);
                    }
                }}
            >
                <DialogContent data-test="campaign-preview-unsubscribe">
                    <DialogHeader>
                        <DialogTitle>Unsubscribe preview</DialogTitle>
                        <DialogDescription>
                            Recipients who click Unsubscribe see a confirmation
                            like this. This preview does not opt anyone out.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col items-center gap-3 rounded-lg border bg-muted/40 p-6 text-center">
                        <div className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <HugeiconsIcon icon={MailRemove01Icon} />
                        </div>
                        <p className="font-medium">Unsubscribe</p>
                        <p className="text-sm text-muted-foreground">
                            Stop sending this audience to{' '}
                            {preview.recipient.email}?
                        </p>
                        <Button type="button" disabled>
                            Confirm unsubscribe
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={openedLink === 'subscribe'}
                onOpenChange={(open) => {
                    if (!open) {
                        setOpenedLink(null);
                    }
                }}
            >
                <DialogContent data-test="campaign-preview-subscribe">
                    <DialogHeader>
                        <DialogTitle>Subscribe preview</DialogTitle>
                        <DialogDescription>
                            Recipients who click Subscribe would open this
                            audience&apos;s public form. Publish a subscribe
                            form to open the real page from preview. Nobody is
                            subscribed here.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col items-center gap-3 rounded-lg border bg-muted/40 p-6 text-center">
                        <div className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <HugeiconsIcon icon={UserAdd01Icon} />
                        </div>
                        <p className="font-medium">Subscribe</p>
                        <p className="text-sm text-muted-foreground">
                            Join this audience from the public subscribe form.
                        </p>
                        <Button type="button" disabled>
                            Subscribe
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={openedLink === 'web-view'}
                onOpenChange={(open) => {
                    if (!open) {
                        setOpenedLink(null);
                    }
                }}
            >
                <DialogContent
                    className="flex max-h-[calc(100svh-2rem)] w-lg flex-col overflow-hidden"
                    data-test="campaign-preview-web-view"
                >
                    <DialogHeader>
                        <DialogTitle>View in browser</DialogTitle>
                        <DialogDescription>
                            Recipients who open the hosted copy see this
                            personalized email in their browser.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="min-h-0 flex-1 overflow-auto rounded-md border bg-background">
                        <iframe
                            title="Hosted copy preview"
                            srcDoc={preview.html}
                            sandbox="allow-same-origin"
                            className="block min-h-96 w-full border-0"
                        />
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
