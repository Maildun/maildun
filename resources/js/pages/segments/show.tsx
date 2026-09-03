import {
    Delete02Icon,
    Edit03Icon,
    MoreHorizontalIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Paginator } from '@/components/paginator';
import { SegmentRuleFields } from '@/components/segment-rule-fields';
import {
    SubscriberHoverCard,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatRelativeTime } from '@/lib/format';
import {
    index as audiencesIndex,
    show as showAudience,
} from '@/routes/audiences';
import { destroy, show, update } from '@/routes/audiences/segments';
import { show as showSubscriber } from '@/routes/audiences/subscribers';
import type { Paginated, Segment, Subscriber } from '@/types/audiences';

type Props = {
    audience: { uuid: string; name: string };
    segment: Segment;
    subscribers: Paginated<Subscriber>;
    forms: { uuid: string; name: string }[];
    canManage: boolean;
    currentTeam: { slug: string };
};

export default function SegmentShow({
    audience,
    segment,
    subscribers,
    forms,
    canManage,
    currentTeam,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const routeArgs = [currentTeam.slug, audience.uuid, segment.uuid] as [
        string,
        string,
        string,
    ];

    return (
        <>
            <Head title={segment.name} />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {segment.name}
                        </h1>
                        {segment.description && (
                            <p className="text-sm text-muted-foreground">
                                {segment.description}
                            </p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            {subscribers.total} subscribers match this dynamic
                            segment.{' '}
                            {segment.rules_synced_at
                                ? `Rules synced ${formatRelativeTime(segment.rules_synced_at)} ago.`
                                : 'Rules have not synced yet.'}
                        </p>
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                render={
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        aria-label="Segment actions"
                                    />
                                }
                            >
                                <HugeiconsIcon icon={MoreHorizontalIcon} />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuGroup>
                                    <DropdownMenuItem
                                        onClick={() => setEditOpen(true)}
                                    >
                                        <HugeiconsIcon icon={Edit03Icon} />
                                        {canManage
                                            ? 'Edit rules'
                                            : 'View rules'}
                                    </DropdownMenuItem>
                                    {canManage && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() =>
                                                    setDeleteOpen(true)
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={Delete02Icon}
                                                />
                                                Delete
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Delete this segment?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Subscribers are not deleted; only the saved
                                rules are removed.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                variant="destructive"
                                onClick={() =>
                                    router.delete(destroy.url(routeArgs))
                                }
                            >
                                Delete segment
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {subscribers.data.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        No subscribers match these rules.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Subscriber</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Source</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {subscribers.data.map((subscriber) => (
                                <TableRow
                                    key={subscriber.uuid}
                                    data-test="subscriber-row"
                                >
                                    <TableCell className="max-w-0">
                                        <SubscriberHoverCard
                                            subscriber={subscriber}
                                            href={showSubscriber.url([
                                                currentTeam.slug,
                                                audience.uuid,
                                                subscriber.uuid,
                                            ])}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {subscriber.status === 'subscribed' ? (
                                            <Badge variant="success">
                                                Subscribed
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Unsubscribed
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {subscriberSourceLabel(subscriber)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Paginator paginator={subscribers} />
            </div>

            <SegmentEditDialog
                open={editOpen}
                onOpenChange={setEditOpen}
                routeArgs={routeArgs}
                segment={segment}
                forms={forms}
                canManage={canManage}
            />
        </>
    );
}

function SegmentEditDialog({
    open,
    onOpenChange,
    routeArgs,
    segment,
    forms,
    canManage,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    routeArgs: [string, string, string];
    segment: Segment;
    forms: { uuid: string; name: string }[];
    canManage: boolean;
}) {
    const form = useForm({
        name: segment.name,
        description: segment.description || '',
        match_type: segment.match_type,
        rules: segment.rules,
    });

    useEffect(() => {
        if (open) {
            form.setData({
                name: segment.name,
                description: segment.description || '',
                match_type: segment.match_type,
                rules: segment.rules,
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, segment]);

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(update.url(routeArgs), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-2xl">
                <DialogHeader>
                    <DialogTitle>Segment rules</DialogTitle>
                    <DialogDescription>
                        Match{' '}
                        {form.data.match_type === 'all'
                            ? 'every saved rule (AND)'
                            : 'at least one saved rule (OR)'}
                        . Subscribers are recomputed automatically.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={save}>
                    <SegmentRuleFields
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors}
                        forms={forms}
                        disabled={!canManage}
                    />
                    {canManage && (
                        <DialogFooter className="mt-6">
                            <Button
                                type="submit"
                                disabled={form.processing || !form.isDirty}
                            >
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Save segment
                            </Button>
                        </DialogFooter>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}

SegmentShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Audiences',
            href: audiencesIndex(props.currentTeam.slug),
        },
        {
            title: props.audience.name,
            href: showAudience([props.currentTeam.slug, props.audience.uuid]),
        },
        {
            title: props.segment.name,
            href: show([
                props.currentTeam.slug,
                props.audience.uuid,
                props.segment.uuid,
            ]),
        },
    ],
});
