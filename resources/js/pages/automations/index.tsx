import {
    Activity03Icon,
    Add01Icon,
    Delete02Icon,
    Edit03Icon,
    MoreHorizontalIcon,
    NodeEditIcon,
    PauseIcon,
    PlayIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CreateAutomationDialog from '@/components/create-automation-dialog';
import DeleteAutomationModal from '@/components/delete-automation-modal';
import { Paginator } from '@/components/paginator';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/toast';
import { formatRelativeTime } from '@/lib/format';
import { activate, activity, edit, index, pause } from '@/routes/automations';
import type { AutomationStatus, AutomationSummary } from '@/types';
import type { Paginated } from '@/types/audiences';

type Props = {
    automations: Paginated<AutomationSummary>;
    canManage: boolean;
};

const STATUS_LABELS: Record<AutomationStatus, string> = {
    draft: 'Draft',
    active: 'Active',
    paused: 'Paused',
};

const STATUS_VARIANTS: Record<
    AutomationStatus,
    'success' | 'secondary' | 'amber'
> = {
    draft: 'secondary',
    active: 'success',
    paused: 'amber',
};

export default function AutomationsIndex({ automations, canManage }: Props) {
    const { currentTeam } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [automationToDelete, setAutomationToDelete] =
        useState<AutomationSummary | null>(null);

    if (!currentTeam) {
        return null;
    }

    const changeStatus = (
        automation: AutomationSummary,
        next: 'activate' | 'pause',
    ) => {
        const route = next === 'activate' ? activate : pause;

        router.post(
            route.url([currentTeam.slug, automation.uuid]),
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.add({
                        type: 'error',
                        title:
                            next === 'activate'
                                ? 'This automation is not ready to run yet.'
                                : 'Failed to pause the automation.',
                        description:
                            Object.values(errors).flat().join(' ') || undefined,
                    }),
            },
        );
    };

    return (
        <>
            <Head title="Automations" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Automations
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Flows that run on their own when someone subscribes,
                            unsubscribes, gets tagged, or your API calls in.
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            data-test="create-automation-button"
                            onClick={() => setCreateOpen(true)}
                        >
                            <HugeiconsIcon
                                icon={Add01Icon}
                                data-icon="inline-start"
                            />
                            New automation
                        </Button>
                    )}
                </div>

                {automations.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={NodeEditIcon} />
                            </EmptyMedia>
                            <EmptyTitle>No automations yet</EmptyTitle>
                            <EmptyDescription>
                                {canManage
                                    ? 'Build your first flow: pick a trigger, then add the steps that follow it.'
                                    : 'Automations built by your team will appear here.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManage && (
                            <EmptyContent>
                                <Button
                                    data-test="create-automation-button"
                                    onClick={() => setCreateOpen(true)}
                                >
                                    Create automation
                                </Button>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Status</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Trigger</TableHead>
                                <TableHead className="text-right">
                                    In flight
                                </TableHead>
                                <TableHead className="text-right">
                                    Enrolled
                                </TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {automations.data.map((automation) => (
                                <TableRow
                                    key={automation.uuid}
                                    data-test="automation-row"
                                >
                                    <TableCell>
                                        <Badge
                                            data-test="automation-status"
                                            variant={
                                                STATUS_VARIANTS[
                                                    automation.status
                                                ]
                                            }
                                        >
                                            {STATUS_LABELS[automation.status]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex min-w-0 flex-col">
                                            <Link
                                                href={edit([
                                                    currentTeam.slug,
                                                    automation.uuid,
                                                ])}
                                                prefetch
                                                className="font-medium underline-offset-4 hover:underline"
                                                data-test="automation-name-link"
                                            >
                                                {automation.name}
                                            </Link>
                                            {automation.description && (
                                                <span className="truncate text-muted-foreground">
                                                    {automation.description}
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {automation.trigger_label}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {automation.running_count}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {automation.enrolled_count}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {automation.updated_at
                                            ? formatRelativeTime(
                                                  automation.updated_at,
                                              )
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                render={
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        data-test="automation-actions-button"
                                                        aria-label={`Actions for ${automation.name}`}
                                                    />
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={MoreHorizontalIcon}
                                                />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuGroup>
                                                    <DropdownMenuItem
                                                        render={
                                                            <Link
                                                                href={edit([
                                                                    currentTeam.slug,
                                                                    automation.uuid,
                                                                ])}
                                                                prefetch
                                                            />
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={Edit03Icon}
                                                        />
                                                        {canManage
                                                            ? 'Edit'
                                                            : 'View'}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        data-test="automation-activity-link"
                                                        render={
                                                            <Link
                                                                href={activity([
                                                                    currentTeam.slug,
                                                                    automation.uuid,
                                                                ])}
                                                                prefetch
                                                            />
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={
                                                                Activity03Icon
                                                            }
                                                        />
                                                        Activity
                                                    </DropdownMenuItem>
                                                    {canManage && (
                                                        <>
                                                            {automation.status ===
                                                            'active' ? (
                                                                <DropdownMenuItem
                                                                    data-test="pause-automation-button"
                                                                    onClick={() =>
                                                                        changeStatus(
                                                                            automation,
                                                                            'pause',
                                                                        )
                                                                    }
                                                                >
                                                                    <HugeiconsIcon
                                                                        icon={
                                                                            PauseIcon
                                                                        }
                                                                    />
                                                                    Pause
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem
                                                                    data-test="activate-automation-button"
                                                                    onClick={() =>
                                                                        changeStatus(
                                                                            automation,
                                                                            'activate',
                                                                        )
                                                                    }
                                                                >
                                                                    <HugeiconsIcon
                                                                        icon={
                                                                            PlayIcon
                                                                        }
                                                                    />
                                                                    Activate
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                data-test="delete-automation-button"
                                                                onClick={() =>
                                                                    setAutomationToDelete(
                                                                        automation,
                                                                    )
                                                                }
                                                            >
                                                                <HugeiconsIcon
                                                                    icon={
                                                                        Delete02Icon
                                                                    }
                                                                />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Paginator paginator={automations} />
            </div>

            {canManage && (
                <>
                    <CreateAutomationDialog
                        teamSlug={currentTeam.slug}
                        open={createOpen}
                        onOpenChange={setCreateOpen}
                    />

                    <DeleteAutomationModal
                        teamSlug={currentTeam.slug}
                        automation={automationToDelete}
                        open={automationToDelete !== null}
                        onOpenChange={(open) =>
                            setAutomationToDelete(
                                open ? automationToDelete : null,
                            )
                        }
                    />
                </>
            )}
        </>
    );
}

AutomationsIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Automations',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
