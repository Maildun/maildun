import {
    Mail01Icon,
    SourceCodeSquareIcon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { Separator } from '@/components/ui/separator';
import { formatRelativeTime } from '@/lib/format';
import { tagBadgeVariant } from '@/lib/tags';
import type { Subscriber } from '@/types/audiences';

export function subscriberDisplayName(subscriber: Subscriber): string {
    return (
        [subscriber.first_name, subscriber.last_name]
            .filter(Boolean)
            .join(' ') || 'Unnamed contact'
    );
}

export function subscriberSourceLabel(subscriber: Subscriber): string {
    return (
        subscriber.source_form?.name ||
        (subscriber.source === 'form'
            ? 'Subscribe form'
            : subscriber.source === 'api'
              ? 'API'
              : 'Manual')
    );
}

export function subscriberSourceIcon(subscriber: Subscriber) {
    return subscriber.source === 'form'
        ? Mail01Icon
        : subscriber.source === 'api'
          ? SourceCodeSquareIcon
          : UserIcon;
}

export function SubscriberHoverCard({
    subscriber,
    href,
    canManage = false,
    onEdit,
    open,
    onOpenChange,
}: {
    subscriber: Subscriber;
    href?: string;
    canManage?: boolean;
    onEdit?: () => void;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const name = subscriberDisplayName(subscriber);
    const source = subscriberSourceLabel(subscriber);
    const initial = subscriber.email.charAt(0).toUpperCase();

    return (
        <HoverCard open={open} onOpenChange={onOpenChange}>
            <HoverCardTrigger
                delay={200}
                closeDelay={200}
                type={href ? undefined : 'button'}
                render={href ? <Link href={href} prefetch /> : undefined}
                aria-label={href ? `View ${name}` : `Preview ${name}`}
                data-test="subscriber-hover-trigger"
                className="font-inherit flex max-w-full min-w-0 items-center gap-3 rounded-md bg-transparent p-0 text-left text-inherit outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <Avatar className="size-8">
                    <AvatarImage src={subscriber.avatar} alt="" />
                    <AvatarFallback>{initial}</AvatarFallback>
                </Avatar>
                <span className="flex min-w-0 flex-col">
                    <span className="truncate font-medium hover:underline">
                        {name}
                    </span>
                    <span className="truncate text-muted-foreground">
                        {subscriber.email}
                    </span>
                </span>
            </HoverCardTrigger>
            <HoverCardContent
                side="right"
                align="start"
                className="flex w-80 flex-col gap-3"
                data-test="subscriber-hover-card"
            >
                <div className="flex items-start gap-3">
                    <Avatar size="lg">
                        <AvatarImage src={subscriber.avatar} alt="" />
                        <AvatarFallback>{initial}</AvatarFallback>
                    </Avatar>
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <p className="truncate font-medium">{name}</p>
                        <p className="truncate text-muted-foreground">
                            {subscriber.email}
                        </p>
                    </div>
                </div>
                <Separator />
                <dl className="flex flex-col gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <dt className="text-muted-foreground">Status</dt>
                        <dd>
                            {subscriber.status === 'subscribed' ? (
                                <Badge variant="success">Subscribed</Badge>
                            ) : (
                                <Badge variant="secondary">Unsubscribed</Badge>
                            )}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <dt className="text-muted-foreground">Source</dt>
                        <dd className="flex items-center gap-1.5">
                            <HugeiconsIcon
                                icon={subscriberSourceIcon(subscriber)}
                                className="size-3.5"
                            />
                            {source}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <dt className="text-muted-foreground">Subscribed</dt>
                        <dd
                            title={
                                subscriber.subscribed_at
                                    ? new Date(
                                          subscriber.subscribed_at,
                                      ).toLocaleString()
                                    : undefined
                            }
                        >
                            {subscriber.subscribed_at
                                ? formatRelativeTime(subscriber.subscribed_at)
                                : '—'}
                        </dd>
                    </div>
                </dl>
                {subscriber.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {subscriber.tags.map((tag) => (
                            <Badge
                                key={tag.uuid}
                                variant={tagBadgeVariant(tag.color)}
                            >
                                {tag.name}
                            </Badge>
                        ))}
                    </div>
                )}
                {(href || (canManage && onEdit)) && (
                    <>
                        <Separator />
                        <div className="flex gap-2">
                            {href && (
                                <Button
                                    size="sm"
                                    className="flex-1"
                                    render={<Link href={href} prefetch />}
                                    data-test="subscriber-hover-profile"
                                >
                                    Profile
                                </Button>
                            )}
                            {canManage && onEdit && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="flex-1"
                                    data-test="subscriber-hover-edit"
                                    onClick={onEdit}
                                >
                                    Edit
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </HoverCardContent>
        </HoverCard>
    );
}
