import { Building06Icon, UserGroupIcon } from '@hugeicons/core-free-icons';
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
import type { ContactSummary } from '@/types/contacts';

export function contactDisplayName(contact: ContactSummary): string {
    return (
        [contact.first_name, contact.last_name].filter(Boolean).join(' ') ||
        contact.email
    );
}

export function ContactHoverCard({
    contact,
    href,
    canManage = false,
    onEdit,
    open,
    onOpenChange,
}: {
    contact: ContactSummary;
    href: string;
    canManage?: boolean;
    onEdit?: () => void;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const name = contactDisplayName(contact);
    const initial = (contact.first_name ?? contact.email)
        .charAt(0)
        .toUpperCase();

    return (
        <HoverCard open={open} onOpenChange={onOpenChange}>
            <HoverCardTrigger
                delay={200}
                closeDelay={200}
                render={<Link href={href} prefetch />}
                aria-label={`View ${name}`}
                data-test="contact-hover-trigger"
                className="font-inherit flex max-w-full min-w-0 items-center gap-3 rounded-md bg-transparent p-0 text-left text-inherit outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <Avatar className="size-8">
                    <AvatarImage src={contact.avatar} alt="" />
                    <AvatarFallback>{initial}</AvatarFallback>
                </Avatar>
                <span className="flex min-w-0 flex-col">
                    <span className="truncate font-medium hover:underline">
                        {name}
                    </span>
                    <span className="truncate text-xs text-muted-foreground">
                        {contact.email}
                    </span>
                </span>
            </HoverCardTrigger>
            <HoverCardContent
                side="right"
                align="start"
                className="flex w-80 flex-col gap-3"
                data-test="contact-hover-card"
            >
                <div className="flex items-start gap-3">
                    <Avatar size="lg">
                        <AvatarImage src={contact.avatar} alt="" />
                        <AvatarFallback>{initial}</AvatarFallback>
                    </Avatar>
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <p className="truncate font-medium">{name}</p>
                        <p className="truncate text-muted-foreground">
                            {contact.email}
                        </p>
                    </div>
                </div>
                <Separator />
                <dl className="flex flex-col gap-2">
                    <div className="flex items-center justify-between gap-3">
                        <dt className="text-muted-foreground">Company</dt>
                        <dd className="flex min-w-0 items-center gap-1.5">
                            <HugeiconsIcon
                                icon={Building06Icon}
                                className="size-3.5 shrink-0"
                            />
                            <span className="truncate">
                                {contact.company?.name ?? 'No company'}
                            </span>
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <dt className="text-muted-foreground">Audiences</dt>
                        <dd className="flex items-center gap-1.5">
                            <HugeiconsIcon
                                icon={UserGroupIcon}
                                className="size-3.5"
                            />
                            {contact.audiences_count}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <dt className="text-muted-foreground">Created</dt>
                        <dd
                            title={
                                contact.created_at
                                    ? new Date(
                                          contact.created_at,
                                      ).toLocaleString()
                                    : undefined
                            }
                        >
                            {contact.created_at
                                ? formatRelativeTime(contact.created_at)
                                : '—'}
                        </dd>
                    </div>
                </dl>
                {contact.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {contact.tags.map((tag) => (
                            <Badge
                                key={tag.uuid}
                                variant={tagBadgeVariant(tag.color)}
                            >
                                {tag.name}
                            </Badge>
                        ))}
                    </div>
                )}
                <Separator />
                <div className="flex gap-2">
                    <Button
                        size="sm"
                        className="flex-1"
                        render={<Link href={href} prefetch />}
                        data-test="contact-hover-profile"
                    >
                        Profile
                    </Button>
                    {canManage && onEdit && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="flex-1"
                            data-test="contact-hover-edit"
                            onClick={onEdit}
                        >
                            Edit
                        </Button>
                    )}
                </div>
            </HoverCardContent>
        </HoverCard>
    );
}
