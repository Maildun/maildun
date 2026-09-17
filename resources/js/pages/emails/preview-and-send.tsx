import {
    ArrowLeft01Icon,
    ArrowRight01Icon,
    InformationCircleIcon,
    MailSend01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useHttp,
    usePage,
} from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { SyntheticEvent } from 'react';
import PreviewWidthTabs from '@/components/preview-width-tabs';
import type { PreviewWidth } from '@/components/preview-width-tabs';
import SendTestEmailDialog from '@/components/send-test-email-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { composePreview, edit as editCampaign, send } from '@/routes/emails';

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
    };
    recipientCount: number;
    preview: CampaignPreview;
};

const ZOOM_LEVELS = ['75', '100', '125'] as const;
type ZoomLevel = (typeof ZOOM_LEVELS)[number];

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
    const [testOpen, setTestOpen] = useState(false);
    const [previewDocumentHeight, setPreviewDocumentHeight] = useState(0);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestRequest = useRef(0);
    const previewHeightObserver = useRef<ResizeObserver | null>(null);
    const previewRequest = useHttp<Record<string, never>, CampaignPreview>({});

    setLayoutProps({ fullscreen: true });

    useEffect(() => {
        return () => {
            previewHeightObserver.current?.disconnect();
        };
    }, []);

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
                onFinish: () => setSending(false),
            },
        );
    };

    const recipientLabels = preview.recipients.map(
        (recipient) => recipient.label,
    );
    const previewFrameWidth = previewWidth === 'desktop' ? 600 : 375;
    const previewScale = Number(zoom) / 100;

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
                            onClick={handleSend}
                            data-test="confirm-send-campaign"
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
                                <ComboboxContent
                                    align="end"
                                    className="w-96"
                                >
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
                                                        <span className="break-all text-xs leading-4 text-muted-foreground">
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

                <main className="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden bg-muted/40 p-4 sm:p-8">
                    {previewError || sendError ? (
                        <div className="mx-auto flex w-full max-w-3xl shrink-0 flex-col gap-3">
                            {previewError ? (
                                <Alert variant="destructive">
                                    <AlertTitle>Preview unavailable</AlertTitle>
                                    <AlertDescription>
                                        {previewError}
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                            {sendError ? (
                                <Alert variant="destructive">
                                    <AlertTitle>Campaign not queued</AlertTitle>
                                    <AlertDescription>
                                        {sendError}
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                        </div>
                    ) : null}

                    <div
                        className="mx-auto min-h-0 w-fit max-w-full flex-1 overflow-auto rounded-lg border bg-background shadow-sm"
                        data-test="campaign-preview-canvas"
                    >
                        <div
                            className="overflow-hidden"
                            style={{
                                width: previewFrameWidth * previewScale,
                                height:
                                    previewDocumentHeight > 0
                                        ? previewDocumentHeight * previewScale
                                        : undefined,
                            }}
                        >
                            <iframe
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
                        </div>
                    </div>
                </main>
            </div>

            <SendTestEmailDialog
                teamSlug={currentTeam.slug}
                emailUuid={campaign.uuid}
                defaultAddress={auth.user.email}
                open={testOpen}
                onOpenChange={setTestOpen}
            />
        </>
    );
}
