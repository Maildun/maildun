import {
    Calendar01Icon,
    Delete02Icon,
    Edit03Icon,
    InformationCircleIcon,
    Mail01Icon,
    MailAtSign02Icon,
    MailOpen01Icon,
    MailReceive01Icon,
    MoreHorizontalIcon,
    MouseLeftClick01Icon,
    NodeEditIcon,
    Tag01Icon,
    UserGroupIcon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Children, Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { Paginator } from '@/components/paginator';
import { SubscriberDialog } from '@/components/subscriber-dialog';
import {
    subscriberDisplayName,
    subscriberSourceLabel,
} from '@/components/subscriber-hover-card';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldLabel } from '@/components/ui/field';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    DELIVERY_STATUS_LABELS,
    deliveryStatusVariant,
} from '@/lib/email-status';
import { formatRelativeTime } from '@/lib/format';
import { tagBadgeVariant } from '@/lib/tags';
import { cn } from '@/lib/utils';
import {
    index as audiencesIndex,
    show as showAudience,
} from '@/routes/audiences';
import { show as showSegment } from '@/routes/audiences/segments';
import {
    destroy as destroySubscriber,
    resubscribe,
    show,
    unsubscribe,
} from '@/routes/audiences/subscribers';
import { edit as editAutomation } from '@/routes/automations';
import { show as showCampaign } from '@/routes/emails';
import type {
    Paginated,
    SubscriberAttributeValue,
    SubscriberAutomationRun,
    SubscriberEmailFilter,
    SubscriberEmailStats,
    SubscriberProfile,
    SubscriberReceivedEmail,
    SubscriberShowFilters,
    Tag,
} from '@/types/audiences';

type Props = {
    audience: { uuid: string; name: string };
    subscriber: SubscriberProfile;
    attributes: SubscriberAttributeValue[];
    segments: { uuid: string; name: string }[];
    stats: SubscriberEmailStats;
    emails: Paginated<SubscriberReceivedEmail>;
    automations: SubscriberAutomationRun[];
    tags: Tag[];
    filters: SubscriberShowFilters;
    canManage: boolean;
};

const AUTOMATION_RUN_LABELS: Record<string, string> = {
    pending: 'Pending',
    running: 'Running',
    waiting: 'Waiting',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
};

const EMAIL_FILTERS: { value: SubscriberEmailFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'opened', label: 'Opened' },
    { value: 'clicked', label: 'Clicked' },
    { value: 'bounced', label: 'Bounced' },
    { value: 'failed', label: 'Failed' },
];

export default function SubscriberShow({
    audience,
    subscriber,
    attributes,
    segments,
    stats,
    emails,
    automations,
    tags,
    filters,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [editOpen, setEditOpen] = useState(false);
    const [lifecycleOpen, setLifecycleOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [consentConfirmed, setConsentConfirmed] = useState(false);

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];
    const subscriberArgs: [string, string, string] = [
        ...routeArgs,
        subscriber.uuid,
    ];
    const name = subscriberDisplayName(subscriber);
    const source = subscriberSourceLabel(subscriber);
    const isSubscribed = subscriber.status === 'subscribed';

    const visitEmails = (status: SubscriberEmailFilter) => {
        router.get(
            show.url(subscriberArgs, {
                query: status === 'all' ? {} : { status },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={name} />
            <div
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8"
                data-test="subscriber-profile"
            >
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex items-start gap-3">
                        <Avatar size="lg">
                            <AvatarImage src={subscriber.avatar} alt="" />
                            <AvatarFallback>
                                {subscriber.email.charAt(0).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {name}
                                </h1>
                                {isSubscribed ? (
                                    <Badge variant="success">Subscribed</Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        Unsubscribed
                                    </Badge>
                                )}
                            </div>
                            <p className="truncate text-sm text-muted-foreground">
                                {subscriber.email}
                            </p>
                        </div>
                    </div>
                    {canManage && (
                        <div className="flex shrink-0 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                data-test="subscriber-profile-edit"
                                onClick={() => setEditOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={Edit03Icon}
                                    data-icon="inline-start"
                                />
                                Edit
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    isSubscribed ? 'destructive' : 'outline'
                                }
                                onClick={() => setLifecycleOpen(true)}
                            >
                                {isSubscribed ? 'Unsubscribe' : 'Resubscribe'}
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    render={
                                        <Button
                                            size="icon"
                                            variant="outline"
                                            aria-label="Subscriber actions"
                                        />
                                    }
                                >
                                    <HugeiconsIcon icon={MoreHorizontalIcon} />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuGroup>
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onClick={() => setDeleteOpen(true)}
                                        >
                                            <HugeiconsIcon
                                                icon={Delete02Icon}
                                            />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    )}
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="font-heading text-base font-medium">
                        Activity
                    </h2>
                    <Card className="gap-0 py-0">
                        <PropertyList>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={MailReceive01Icon}
                                            />
                                        }
                                    >
                                        Emails received
                                    </IconLabel>
                                }
                                href="#received-emails"
                            >
                                {stats.received.toLocaleString()}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={MailOpen01Icon}
                                            />
                                        }
                                    >
                                        Opened
                                    </IconLabel>
                                }
                                href="#received-emails"
                            >
                                {stats.opened.toLocaleString()}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={MouseLeftClick01Icon}
                                            />
                                        }
                                    >
                                        Clicked
                                    </IconLabel>
                                }
                                href="#received-emails"
                            >
                                {stats.clicked.toLocaleString()}
                            </PropertyRow>
                        </PropertyList>
                        <CardFooter className="justify-between gap-3 text-muted-foreground">
                            <p>{activityDetail(stats, source)}</p>
                            {stats.bounced > 0 && (
                                <p>{stats.bounced.toLocaleString()} bounced</p>
                            )}
                        </CardFooter>
                    </Card>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="font-heading text-base font-medium">
                        Details
                    </h2>
                    <Card className="gap-0 py-0">
                        <PropertyList>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon icon={Mail01Icon} />
                                        }
                                    >
                                        Email
                                    </IconLabel>
                                }
                            >
                                {subscriber.email}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={<HugeiconsIcon icon={UserIcon} />}
                                    >
                                        First name
                                    </IconLabel>
                                }
                            >
                                {subscriber.first_name || '—'}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={<HugeiconsIcon icon={UserIcon} />}
                                    >
                                        Last name
                                    </IconLabel>
                                }
                            >
                                {subscriber.last_name || '—'}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={<HugeiconsIcon icon={UserIcon} />}
                                    >
                                        Source
                                    </IconLabel>
                                }
                            >
                                {source}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={Calendar01Icon}
                                            />
                                        }
                                    >
                                        Subscribed
                                    </IconLabel>
                                }
                                title={absoluteTime(subscriber.subscribed_at)}
                            >
                                {subscriber.subscribed_at
                                    ? formatRelativeTime(
                                          subscriber.subscribed_at,
                                      )
                                    : '—'}
                            </PropertyRow>
                            {subscriber.unsubscribed_at && (
                                <PropertyRow
                                    label={
                                        <IconLabel
                                            icon={
                                                <HugeiconsIcon
                                                    icon={Calendar01Icon}
                                                />
                                            }
                                        >
                                            Unsubscribed
                                        </IconLabel>
                                    }
                                    title={absoluteTime(
                                        subscriber.unsubscribed_at,
                                    )}
                                >
                                    {formatRelativeTime(
                                        subscriber.unsubscribed_at,
                                    )}
                                </PropertyRow>
                            )}
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon icon={Tag01Icon} />
                                        }
                                    >
                                        Tags
                                    </IconLabel>
                                }
                            >
                                {subscriber.tags.length > 0 ? (
                                    <span className="flex flex-wrap justify-end gap-1">
                                        {subscriber.tags.map((tag) => (
                                            <Badge
                                                key={tag.uuid}
                                                variant={tagBadgeVariant(
                                                    tag.color,
                                                )}
                                            >
                                                {tag.name}
                                            </Badge>
                                        ))}
                                    </span>
                                ) : (
                                    '—'
                                )}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={UserGroupIcon}
                                            />
                                        }
                                    >
                                        Matching segments
                                    </IconLabel>
                                }
                            >
                                {segments.length > 0 ? (
                                    <span className="flex flex-wrap justify-end gap-1">
                                        {segments.map((segment) => (
                                            <Badge
                                                key={segment.uuid}
                                                variant="secondary"
                                                render={
                                                    <Link
                                                        href={showSegment.url([
                                                            currentTeam.slug,
                                                            audience.uuid,
                                                            segment.uuid,
                                                        ])}
                                                        prefetch
                                                    />
                                                }
                                            >
                                                {segment.name}
                                            </Badge>
                                        ))}
                                    </span>
                                ) : (
                                    'Not in any segment'
                                )}
                            </PropertyRow>
                        </PropertyList>

                        {attributes.length > 0 && (
                            <>
                                <Separator />
                                <NestedPanel
                                    title="Custom attributes"
                                    icon={
                                        <HugeiconsIcon
                                            icon={InformationCircleIcon}
                                        />
                                    }
                                >
                                    <PropertyList>
                                        {attributes.map((attribute) => (
                                            <PropertyRow
                                                key={attribute.uuid}
                                                label={attribute.name}
                                                className="px-3"
                                            >
                                                {formatAttributeValue(
                                                    attribute,
                                                ) || '—'}
                                            </PropertyRow>
                                        ))}
                                    </PropertyList>
                                </NestedPanel>
                            </>
                        )}

                        <Separator />
                        <NestedPanel title="Consent">
                            <PropertyList>
                                <PropertyRow
                                    label="Recorded"
                                    title={absoluteTime(
                                        subscriber.consented_at,
                                    )}
                                    className="px-3"
                                >
                                    {subscriber.consented_at
                                        ? formatRelativeTime(
                                              subscriber.consented_at,
                                          )
                                        : '—'}
                                </PropertyRow>
                                <PropertyRow
                                    label="IP address"
                                    className="px-3"
                                >
                                    {subscriber.consent_ip || '—'}
                                </PropertyRow>
                                <div className="flex flex-col gap-1 px-3 py-3">
                                    <p className="text-muted-foreground">
                                        Consent text
                                    </p>
                                    <p>{subscriber.consent_text || '—'}</p>
                                </div>
                            </PropertyList>
                        </NestedPanel>
                    </Card>
                </section>

                <section id="received-emails" className="flex flex-col gap-3">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <h2 className="font-heading text-base font-medium">
                            Received emails
                        </h2>
                        <Tabs
                            value={filters.status}
                            onValueChange={(value) => {
                                const next = EMAIL_FILTERS.find(
                                    (filter) => filter.value === value,
                                )?.value;

                                if (next !== undefined) {
                                    visitEmails(next);
                                }
                            }}
                        >
                            <TabsList
                                variant="sliding"
                                aria-label="Filter received emails"
                            >
                                {EMAIL_FILTERS.map((filter) => (
                                    <TabsTrigger
                                        key={filter.value}
                                        value={filter.value}
                                        data-test={`subscriber-email-filter-${filter.value}`}
                                    >
                                        {filter.label}
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>
                    </div>
                    <Card className="gap-0 py-0">
                        {emails.data.length === 0 ? (
                            <Empty className="py-10">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon
                                            icon={MailAtSign02Icon}
                                        />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {filters.status === 'all'
                                            ? 'No emails yet'
                                            : 'No matching emails'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {filters.status === 'all'
                                            ? 'Campaigns sent to this subscriber will appear here.'
                                            : 'Try another delivery filter.'}
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <PropertyList>
                                {emails.data.map((delivery) => (
                                    <div
                                        key={delivery.uuid}
                                        className="flex items-start justify-between gap-4 px-(--card-spacing) py-3"
                                        data-test="subscriber-email-row"
                                    >
                                        <HugeiconsIcon
                                            icon={MailReceive01Icon}
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-label="Email received"
                                        />
                                        <div className="flex min-w-0 flex-col gap-1">
                                            {delivery.campaign?.uuid ? (
                                                <Link
                                                    href={showCampaign.url([
                                                        currentTeam.slug,
                                                        delivery.campaign.uuid,
                                                    ])}
                                                    className="truncate font-medium underline-offset-4 hover:underline"
                                                    prefetch
                                                >
                                                    {delivery.campaign.name}
                                                </Link>
                                            ) : (
                                                <span className="truncate font-medium">
                                                    {delivery.campaign?.name ??
                                                        'Deleted campaign'}
                                                </span>
                                            )}
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-muted-foreground">
                                                <span className="truncate">
                                                    {delivery.campaign
                                                        ?.subject ?? '—'}
                                                </span>
                                                {(delivery.opens > 0 ||
                                                    delivery.clicks > 0) && (
                                                    <span className="flex items-center gap-2 tabular-nums">
                                                        <span className="inline-flex items-center gap-1">
                                                            <HugeiconsIcon
                                                                icon={
                                                                    MailOpen01Icon
                                                                }
                                                                className="size-3.5"
                                                                aria-label="Opened"
                                                            />
                                                            {delivery.opens}
                                                        </span>
                                                        <span className="inline-flex items-center gap-1">
                                                            <HugeiconsIcon
                                                                icon={
                                                                    MouseLeftClick01Icon
                                                                }
                                                                className="size-3.5"
                                                                aria-label="Clicked"
                                                            />
                                                            {delivery.clicks}
                                                        </span>
                                                    </span>
                                                )}
                                            </div>
                                            {delivery.failure_reason && (
                                                <p className="text-xs text-destructive">
                                                    {delivery.failure_reason}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <Badge
                                                variant={deliveryStatusVariant(
                                                    delivery.status,
                                                )}
                                            >
                                                {
                                                    DELIVERY_STATUS_LABELS[
                                                        delivery.status
                                                    ]
                                                }
                                            </Badge>
                                            <p
                                                className="text-muted-foreground"
                                                title={absoluteTime(
                                                    delivery.sent_at,
                                                )}
                                            >
                                                {delivery.sent_at
                                                    ? formatRelativeTime(
                                                          delivery.sent_at,
                                                      )
                                                    : '—'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </PropertyList>
                        )}
                        {emails.last_page > 1 && (
                            <CardFooter>
                                <Paginator paginator={emails} />
                            </CardFooter>
                        )}
                    </Card>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="font-heading text-base font-medium">
                        Automations
                    </h2>
                    <Card className="gap-0 py-0">
                        {automations.length === 0 ? (
                            <Empty className="py-10">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon icon={NodeEditIcon} />
                                    </EmptyMedia>
                                    <EmptyTitle>No automation runs</EmptyTitle>
                                    <EmptyDescription>
                                        Automations this subscriber enters will
                                        show here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <PropertyList>
                                {automations.map((run) => (
                                    <div
                                        key={run.uuid}
                                        className="flex items-start justify-between gap-4 px-(--card-spacing) py-3"
                                        data-test="subscriber-automation-row"
                                    >
                                        <div className="flex min-w-0 flex-col gap-1">
                                            {run.automation_uuid ? (
                                                <Link
                                                    href={editAutomation.url([
                                                        currentTeam.slug,
                                                        run.automation_uuid,
                                                    ])}
                                                    className="truncate font-medium underline-offset-4 hover:underline"
                                                    prefetch
                                                >
                                                    {run.name}
                                                </Link>
                                            ) : (
                                                <span className="truncate font-medium">
                                                    {run.name}
                                                </span>
                                            )}
                                            {run.failure_reason && (
                                                <p className="text-xs text-destructive">
                                                    {run.failure_reason}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <Badge
                                                variant={
                                                    run.status === 'completed'
                                                        ? 'success'
                                                        : run.status ===
                                                            'failed'
                                                          ? 'destructive'
                                                          : 'secondary'
                                                }
                                            >
                                                {AUTOMATION_RUN_LABELS[
                                                    run.status
                                                ] ?? run.status}
                                            </Badge>
                                            <p className="text-muted-foreground">
                                                {run.started_at
                                                    ? formatRelativeTime(
                                                          run.started_at,
                                                      )
                                                    : '—'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </PropertyList>
                        )}
                    </Card>
                </section>
            </div>

            {canManage && (
                <>
                    <SubscriberDialog
                        open={editOpen}
                        onOpenChange={setEditOpen}
                        routeArgs={routeArgs}
                        subscriber={subscriber}
                        tags={tags}
                    />
                    <AlertDialog
                        open={lifecycleOpen}
                        onOpenChange={(open) => {
                            setLifecycleOpen(open);

                            if (!open) {
                                setConsentConfirmed(false);
                            }
                        }}
                    >
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    {isSubscribed
                                        ? 'Unsubscribe this contact?'
                                        : 'Resubscribe this contact?'}
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    {isSubscribed
                                        ? `${subscriber.email} will stop receiving future marketing email.`
                                        : `Confirm that ${subscriber.email} has given permission to receive marketing email again.`}
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            {!isSubscribed && (
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="profile-resubscribe-consent"
                                        checked={consentConfirmed}
                                        onCheckedChange={(checked) =>
                                            setConsentConfirmed(
                                                checked === true,
                                            )
                                        }
                                    />
                                    <FieldLabel htmlFor="profile-resubscribe-consent">
                                        Marketing consent confirmed
                                    </FieldLabel>
                                </Field>
                            )}
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    disabled={
                                        !isSubscribed && !consentConfirmed
                                    }
                                    variant={
                                        isSubscribed ? 'destructive' : 'default'
                                    }
                                    onClick={() =>
                                        router.patch(
                                            isSubscribed
                                                ? unsubscribe.url(
                                                      subscriberArgs,
                                                  )
                                                : resubscribe.url(
                                                      subscriberArgs,
                                                  ),
                                            isSubscribed
                                                ? {}
                                                : { consent_confirmed: true },
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    setLifecycleOpen(false);
                                                    setConsentConfirmed(false);
                                                },
                                            },
                                        )
                                    }
                                >
                                    {isSubscribed
                                        ? 'Unsubscribe'
                                        : 'Resubscribe'}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                    <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Delete subscriber?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    This permanently removes {subscriber.email}{' '}
                                    and its consent record.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    variant="destructive"
                                    onClick={() =>
                                        router.delete(
                                            destroySubscriber.url(
                                                subscriberArgs,
                                            ),
                                        )
                                    }
                                >
                                    Delete permanently
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </>
            )}
        </>
    );
}

SubscriberShow.layout = (props: {
    currentTeam?: { slug: string } | null;
    audience: { uuid: string; name: string };
    subscriber: SubscriberProfile;
}) => ({
    breadcrumbs: [
        {
            title: 'Audiences',
            href: props.currentTeam
                ? audiencesIndex(props.currentTeam.slug)
                : '/',
        },
        {
            title: props.audience.name,
            href: props.currentTeam
                ? showAudience([props.currentTeam.slug, props.audience.uuid])
                : '/',
        },
        {
            title: subscriberDisplayName(props.subscriber),
            href: props.currentTeam
                ? show([
                      props.currentTeam.slug,
                      props.audience.uuid,
                      props.subscriber.uuid,
                  ])
                : '/',
        },
    ],
});

function PropertyList({ children }: { children: ReactNode }) {
    const items = Children.toArray(children).filter(Boolean);

    return (
        <div className="flex flex-col">
            {items.map((item, index) => (
                <Fragment key={index}>
                    {index > 0 && <Separator />}
                    {item}
                </Fragment>
            ))}
        </div>
    );
}

function PropertyRow({
    label,
    title,
    href,
    className,
    children,
}: {
    label: ReactNode;
    title?: string;
    href?: string;
    className?: string;
    children: ReactNode;
}) {
    const value = (
        <span className="text-right" title={title}>
            {children}
        </span>
    );

    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 px-(--card-spacing) py-3',
                className,
            )}
        >
            <span className="text-muted-foreground">{label}</span>
            {href ? (
                <a
                    href={href}
                    className="tabular-nums underline underline-offset-4"
                >
                    {children}
                </a>
            ) : (
                value
            )}
        </div>
    );
}

function IconLabel({
    icon,
    children,
}: {
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <span className="flex items-center gap-2 text-muted-foreground [&>svg]:size-3.5">
            {icon}
            {children}
        </span>
    );
}

function NestedPanel({
    title,
    icon,
    children,
}: {
    title: string;
    icon?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="px-(--card-spacing) py-(--card-spacing)">
            <div className="overflow-hidden rounded-lg bg-muted/50">
                <p className="px-3 pt-3 text-sm font-medium">
                    {icon ? <IconLabel icon={icon}>{title}</IconLabel> : title}
                </p>
                {children}
            </div>
        </div>
    );
}

function formatAttributeValue(
    attribute: SubscriberAttributeValue,
): string | null {
    if (attribute.value === null || attribute.value === '') {
        return null;
    }

    if (attribute.type === 'date') {
        const parsed = new Date(String(attribute.value));

        if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleDateString();
        }
    }

    return String(attribute.value);
}

function absoluteTime(value: string | null): string | undefined {
    return value ? new Date(value).toLocaleString() : undefined;
}

function activityDetail(stats: SubscriberEmailStats, source: string): string {
    if (stats.last_opened_at) {
        return `Last opened ${formatRelativeTime(stats.last_opened_at)}`;
    }

    if (stats.last_clicked_at) {
        return `Last clicked ${formatRelativeTime(stats.last_clicked_at)}`;
    }

    if (stats.last_sent_at) {
        return `Last sent ${formatRelativeTime(stats.last_sent_at)}`;
    }

    return `Joined via ${source}`;
}
