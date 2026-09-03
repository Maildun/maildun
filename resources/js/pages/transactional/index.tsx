import {
    Add01Icon,
    Copy01Icon,
    Delete02Icon,
    Edit03Icon,
    MailSend02Icon,
    MoreHorizontalIcon,
    SourceCodeIcon,
    StatusIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import DeleteTransactionalEmailModal from '@/components/delete-transactional-email-modal';
import EmailTemplatePicker from '@/components/email-template-picker';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
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
import { useListFilters } from '@/hooks/use-list-filters';
import { formatRelativeTime } from '@/lib/format';
import { duplicate, edit, index, store } from '@/routes/transactional_emails';
import type {
    EmailEditorMode,
    EmailTemplateSummary,
    TransactionalEmailSummary,
    TransactionalIndexFilters,
} from '@/types';
import type { Paginated } from '@/types/audiences';

type Props = {
    emails: Paginated<TransactionalEmailSummary>;
    templates: EmailTemplateSummary[];
    filters: TransactionalIndexFilters;
    defaultEditor: EmailEditorMode;
    canManage: boolean;
};

const STATUS_LABELS: Record<TransactionalEmailSummary['status'], string> = {
    draft: 'Draft',
    published: 'Published',
};

const EDITOR_LABELS: Record<EmailEditorMode, string> = {
    html: 'HTML',
    builder: 'EmailBuilder.js',
    plain_text: 'Plain text',
    markdown: 'Markdown',
};

export default function TransactionalIndex({
    emails,
    templates,
    filters,
    defaultEditor,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [composeOpen, setComposeOpen] = useState(false);
    const [emailToDelete, setEmailToDelete] =
        useState<TransactionalEmailSummary | null>(null);
    const { visit, clear, clearAll, hasActiveFilters, searchKey } =
        useListFilters<TransactionalIndexFilters>({
            url: currentTeam ? index.url(currentTeam.slug) : '',
            filters,
            empty: { q: '', status: '', editor: '' },
        });

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Transactional" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Transactional
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Templates you will later trigger one recipient at a
                            time through an API or automation.
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            data-test="compose-transactional-button"
                            onClick={() => setComposeOpen(true)}
                        >
                            <HugeiconsIcon
                                icon={Add01Icon}
                                data-icon="inline-start"
                            />
                            Compose
                        </Button>
                    )}
                </div>

                {(emails.data.length > 0 || hasActiveFilters) && (
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <FilterMenu
                                testId="transactional-filter-button"
                                fields={[
                                    {
                                        key: 'status',
                                        icon: StatusIcon,
                                        label: 'Status',
                                        value: filters.status,
                                        allLabel: 'All statuses',
                                        options: Object.entries(
                                            STATUS_LABELS,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        })),
                                        onValueChange: (status) =>
                                            visit({ status }),
                                        testId: 'transactional-status-filter',
                                    },
                                    {
                                        key: 'editor',
                                        icon: SourceCodeIcon,
                                        label: 'Editor',
                                        value: filters.editor,
                                        allLabel: 'All editors',
                                        options: Object.entries(
                                            EDITOR_LABELS,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        })),
                                        onValueChange: (editor) =>
                                            visit({ editor }),
                                        testId: 'transactional-editor-filter',
                                    },
                                ]}
                            />
                            <ListSearch
                                key={searchKey}
                                value={filters.q}
                                onSearch={(q) => visit({ q })}
                                placeholder="Search transactional emails"
                            />
                        </div>
                        <ActiveFilters
                            filters={[
                                ...(filters.q !== ''
                                    ? [
                                          {
                                              key: 'q',
                                              field: 'Search',
                                              value: filters.q,
                                              onClear: () => clear({ q: '' }),
                                          },
                                      ]
                                    : []),
                                ...(filters.status !== ''
                                    ? [
                                          {
                                              key: 'status',
                                              field: 'Status',
                                              value:
                                                  STATUS_LABELS[
                                                      filters.status as TransactionalEmailSummary['status']
                                                  ] ?? filters.status,
                                              onClear: () =>
                                                  clear({ status: '' }),
                                          },
                                      ]
                                    : []),
                                ...(filters.editor !== ''
                                    ? [
                                          {
                                              key: 'editor',
                                              field: 'Editor',
                                              value:
                                                  EDITOR_LABELS[
                                                      filters.editor as EmailEditorMode
                                                  ] ?? filters.editor,
                                              onClear: () =>
                                                  clear({ editor: '' }),
                                          },
                                      ]
                                    : []),
                            ]}
                            onClearAll={clearAll}
                            clearTestId="clear-transactional-filters"
                        />
                    </div>
                )}

                {emails.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={MailSend02Icon} />
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilters
                                    ? 'No matching transactional emails'
                                    : 'No transactional emails yet'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {hasActiveFilters
                                    ? 'Try another search or filter.'
                                    : canManage
                                      ? 'Compose your first transactional email from a template or an empty canvas.'
                                      : 'Transactional emails drafted by your team will appear here.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManage && !hasActiveFilters && (
                            <EmptyContent>
                                <Button
                                    data-test="compose-transactional-button"
                                    onClick={() => setComposeOpen(true)}
                                >
                                    Compose transactional email
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
                                <TableHead>Subject</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {emails.data.map((email) => (
                                <TableRow
                                    key={email.uuid}
                                    data-test="transactional-row"
                                >
                                    <TableCell>
                                        <Badge
                                            data-test="transactional-status"
                                            variant={
                                                email.status === 'published'
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {STATUS_LABELS[email.status]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex min-w-0 flex-col">
                                            <Link
                                                href={edit([
                                                    currentTeam.slug,
                                                    email.uuid,
                                                ])}
                                                prefetch
                                                className="font-medium underline-offset-4 hover:underline"
                                                data-test="transactional-name-link"
                                            >
                                                {email.name}
                                            </Link>
                                            <span className="font-mono text-muted-foreground">
                                                {email.slug}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>{email.subject}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {email.updated_at
                                            ? formatRelativeTime(
                                                  email.updated_at,
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
                                                        data-test="transactional-actions-button"
                                                        aria-label={`Actions for ${email.name}`}
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
                                                                    email.uuid,
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
                                                    {canManage && (
                                                        <>
                                                            <DropdownMenuItem
                                                                data-test="duplicate-transactional-button"
                                                                onClick={() =>
                                                                    router.post(
                                                                        duplicate.url(
                                                                            [
                                                                                currentTeam.slug,
                                                                                email.uuid,
                                                                            ],
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <HugeiconsIcon
                                                                    icon={
                                                                        Copy01Icon
                                                                    }
                                                                />
                                                                Duplicate
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                data-test="delete-transactional-button"
                                                                onClick={() =>
                                                                    setEmailToDelete(
                                                                        email,
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

                <Paginator paginator={emails} />
            </div>

            {canManage && (
                <>
                    <EmailTemplatePicker
                        teamSlug={currentTeam.slug}
                        templates={templates}
                        defaultEditor={defaultEditor}
                        open={composeOpen}
                        onOpenChange={setComposeOpen}
                        storeUrl={store.url(currentTeam.slug)}
                        title="Compose transactional email"
                        description="Name the email and pick what to start from."
                        emptyLabel="Empty transactional email"
                        submitLabel="Start composing"
                        namePlaceholder="Welcome email"
                        nameTestId="transactional-name-input"
                        submitTestId="create-transactional-submit"
                    />

                    <DeleteTransactionalEmailModal
                        teamSlug={currentTeam.slug}
                        email={emailToDelete}
                        open={emailToDelete !== null}
                        onOpenChange={(open) =>
                            setEmailToDelete(open ? emailToDelete : null)
                        }
                    />
                </>
            )}
        </>
    );
}

TransactionalIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Transactional',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
