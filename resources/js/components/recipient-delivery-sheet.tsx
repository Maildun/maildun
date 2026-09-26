import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    DELIVERY_STATUS_LABELS,
    deliveryStatusVariant,
} from '@/lib/email-status';
import { show as showDelivery } from '@/routes/emails/deliveries';
import type { EmailDeliveryStatus } from '@/types';

type DeliveryDetail = {
    uuid: string;
    email: string;
    name: string | null;
    status: EmailDeliveryStatus;
    failure_reason: string | null;
    failure_code: string | null;
    timeline: { label: string; at: string }[];
    opens: number;
    clicks: number;
    attempts: {
        uuid: string;
        provider: string;
        connection: string | null;
        status: EmailDeliveryStatus;
        failure_reason: string | null;
        attempted_at: string | null;
        sent_at: string | null;
    }[];
    events: { type: string; at: string }[];
};

function formatTimestamp(value: string): string {
    return new Date(value).toLocaleString();
}

/**
 * Everything the report knows about one recipient, loaded when the sheet
 * opens: timeline, each send attempt, and the provider feedback events.
 */
export function RecipientDeliverySheet({
    teamSlug,
    campaignUuid,
    deliveryUuid,
    onClose,
}: {
    teamSlug: string;
    campaignUuid: string;
    deliveryUuid: string | null;
    onClose: () => void;
}) {
    const request = useHttp<Record<string, never>, DeliveryDetail>({});
    const [detail, setDetail] = useState<DeliveryDetail | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (deliveryUuid === null) {
            return;
        }

        void request
            .get(showDelivery.url([teamSlug, campaignUuid, deliveryUuid]), {
                onSuccess: (response) => {
                    setFailed(false);
                    setDetail(response);
                },
            })
            .catch(() => setFailed(true));
        // Load once per opened recipient; the request object changes identity
        // on every render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [deliveryUuid, teamSlug, campaignUuid]);

    const current = detail?.uuid === deliveryUuid ? detail : null;

    return (
        <Sheet
            open={deliveryUuid !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <SheetContent
                side="right"
                className="w-full overflow-y-auto sm:max-w-md"
                data-test="recipient-delivery-sheet"
            >
                <SheetHeader>
                    <SheetTitle>
                        {current?.name ?? current?.email ?? 'Recipient'}
                    </SheetTitle>
                    <SheetDescription>
                        {current?.name ? current.email : 'Delivery details'}
                    </SheetDescription>
                </SheetHeader>

                {current === null ? (
                    <div className="flex justify-center p-6 text-sm text-muted-foreground">
                        {failed ? (
                            'These details could not be loaded.'
                        ) : (
                            <Spinner />
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col gap-5 px-4 pb-6 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant={deliveryStatusVariant(current.status)}
                            >
                                {DELIVERY_STATUS_LABELS[current.status]}
                            </Badge>
                            <span className="text-muted-foreground tabular-nums">
                                {current.opens} opens · {current.clicks} clicks
                            </span>
                        </div>
                        {current.failure_reason ? (
                            <p className="text-destructive">
                                {current.failure_code
                                    ? `${current.failure_code}: `
                                    : ''}
                                {current.failure_reason}
                            </p>
                        ) : null}

                        <section className="flex flex-col gap-2">
                            <h3 className="font-medium">Timeline</h3>
                            {current.timeline.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Nothing has happened yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-1">
                                    {current.timeline.map((entry) => (
                                        <li
                                            key={entry.label}
                                            className="flex justify-between gap-4"
                                        >
                                            <span className="text-muted-foreground">
                                                {entry.label}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatTimestamp(entry.at)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <Separator />

                        <section className="flex flex-col gap-2">
                            <h3 className="font-medium">Send attempts</h3>
                            {current.attempts.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Not handed to a provider yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-3">
                                    {current.attempts.map((attempt) => (
                                        <li
                                            key={attempt.uuid}
                                            className="flex flex-col gap-1"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span>
                                                    {attempt.connection ??
                                                        attempt.provider}
                                                </span>
                                                <Badge
                                                    variant={deliveryStatusVariant(
                                                        attempt.status,
                                                    )}
                                                >
                                                    {
                                                        DELIVERY_STATUS_LABELS[
                                                            attempt.status
                                                        ]
                                                    }
                                                </Badge>
                                            </div>
                                            {attempt.attempted_at ? (
                                                <span className="text-muted-foreground tabular-nums">
                                                    Attempted{' '}
                                                    {formatTimestamp(
                                                        attempt.attempted_at,
                                                    )}
                                                </span>
                                            ) : null}
                                            {attempt.failure_reason ? (
                                                <span className="text-destructive">
                                                    {attempt.failure_reason}
                                                </span>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        {current.events.length > 0 ? (
                            <>
                                <Separator />
                                <section className="flex flex-col gap-2">
                                    <h3 className="font-medium">
                                        Provider feedback
                                    </h3>
                                    <ul className="flex flex-col gap-1">
                                        {current.events.map((event, index) => (
                                            <li
                                                key={`${event.type}-${index}`}
                                                className="flex justify-between gap-4"
                                            >
                                                <span>{event.type}</span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {formatTimestamp(event.at)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            </>
                        ) : null}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
