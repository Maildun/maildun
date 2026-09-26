import {
    Add01Icon,
    Delete02Icon,
    Edit03Icon,
    MailAtSign02Icon,
    MoreHorizontalIcon,
    PieChartIcon,
    SourceCodeIcon,
    StatusIcon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import DeleteEmailModal from '@/components/delete-email-modal';
import EmailTemplatePicker from '@/components/email-template-picker';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
import { Paginator } from '@/components/paginator';
import {
    Avatar,
    AvatarFallback,
    AvatarGroup,
    AvatarGroupCount,
    AvatarImage,
} from '@/components/ui/avatar';
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
import {
    CAMPAIGN_STATUS_LABELS,
    campaignStatusVariant,
} from '@/lib/email-status';
import { formatRelativeTime } from '@/lib/format';
import { index as templatesIndex } from '@/routes/email_templates';
import { edit, index, show } from '@/routes/emails';
import type {
    EmailEditorMode,
    EmailIndexAudienceOption,
    EmailIndexFilters,
    EmailSummary,
    EmailTemplateSummary,
} from '@/types';
import type { Paginated } from '@/types/audiences';

type Props = {
    emails: Paginated<EmailSummary>;
    templates: EmailTemplateSummary[];
    audiences: EmailIndexAudienceOption[];
    filters: EmailIndexFilters;
    defaultEditor: EmailEditorMode;
    canManage: boolean;
};

/**
 * A queued or sending campaign is still handing deliveries to the provider,
 * so it cannot be deleted until it finishes.
 */
/** A scheduled campaign is still an editable draft until its time comes. */
function isDraft(status: EmailSummary['status']): boolean {
    return status === 'draft' || status === 'scheduled';
}

function isSending(status: EmailSummary['status']): boolean {
    return status === 'queued' || status === 'sending';
}

/**
 * While any listed campaign is queued or sending, refresh only the rows so
 * status and progress update without a reload.
 */
function CampaignRowsPoller() {
    usePoll(4000, { only: ['emails'] }, { mode: 'rest' });

    return null;
}

const EDITOR_LABELS: Record<EmailEditorMode, string> = {
    html: 'HTML',
    builder: 'EmailBuilder.js',
    plain_text: 'Plain text',
    markdown: 'Markdown',
};

const RECIPIENT_OVERFLOW_FORMATTER = new Intl.NumberFormat('en', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

function formatRecipientOverflow(count: number): string {
    return RECIPIENT_OVERFLOW_FORMATTER.format(count);
}

export default function EmailsIndex({
    emails,
    templates,
    audiences,
    filters,
    defaultEditor,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [composeOpen, setComposeOpen] = useState(false);
    const hasSendingRow = emails.data.some((email) => isSending(email.status));
    const [emailToDelete, setEmailToDelete] = useState<EmailSummary | null>(
        null,
    );
    const { visit, clear, clearAll, hasActiveFilters, searchKey } =
        useListFilters<EmailIndexFilters>({
            url: currentTeam ? index.url(currentTeam.slug) : '',
            filters,
            empty: { q: '', status: '', editor: '', audience: '' },
        });

    if (!currentTeam) {
        return null;
    }

    const audienceFilterLabel =
        audiences.find((audience) => audience.uuid === filters.audience)
            ?.name ?? filters.audience;

    return (
        <>
            <Head title="Campaigns" />
            {hasSendingRow && <CampaignRowsPoller />}
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Campaigns
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Draft, preview, and test the campaigns this team
                            sends.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <Link
                                    href={templatesIndex(currentTeam.slug)}
                                    prefetch
                                />
                            }
                        >
                            Templates
                        </Button>
                        {canManage && (
                            <Button
                                data-test="compose-email-button"
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
                </div>

                {(emails.data.length > 0 || hasActiveFilters) && (
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <FilterMenu
                                testId="campaign-filter-button"
                                fields={[
                                    {
                                        key: 'status',
                                        icon: StatusIcon,
                                        label: 'Status',
                                        value: filters.status,
                                        allLabel: 'All statuses',
                                        options: Object.entries(
                                            CAMPAIGN_STATUS_LABELS,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        })),
                                        onValueChange: (status) =>
                                            visit({ status }),
                                        testId: 'campaign-status-filter',
                                    },
                                    {
                                        key: 'audience',
                                        icon: UserGroupIcon,
                                        label: 'Audience',
                                        value: filters.audience,
                                        allLabel: 'All audiences',
                                        options: audiences.map((audience) => ({
                                            value: audience.uuid,
                                            label: audience.name,
                                        })),
                                        onValueChange: (audience) =>
                                            visit({ audience }),
                                        testId: 'campaign-audience-filter',
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
                                        testId: 'campaign-editor-filter',
                                    },
                                ]}
                            />
                            <ListSearch
                                key={searchKey}
                                value={filters.q}
                                onSearch={(q) => visit({ q })}
                                placeholder="Search campaigns"
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
                                                  CAMPAIGN_STATUS_LABELS[
                                                      filters.status as EmailSummary['status']
                                                  ] ?? filters.status,
                                              onClear: () =>
                                                  clear({ status: '' }),
                                          },
                                      ]
                                    : []),
                                ...(filters.audience !== ''
                                    ? [
                                          {
                                              key: 'audience',
                                              field: 'Audience',
                                              value: audienceFilterLabel,
                                              onClear: () =>
                                                  clear({ audience: '' }),
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
                            clearTestId="clear-campaign-filters"
                        />
                    </div>
                )}

                {emails.data.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={MailAtSign02Icon} />
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilters
                                    ? 'No matching campaigns'
                                    : 'No campaigns yet'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {hasActiveFilters
                                    ? 'Try another search or filter.'
                                    : canManage
                                      ? 'Compose your first campaign from a template or an empty canvas.'
                                      : 'Campaigns drafted by your team will appear here.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManage && !hasActiveFilters && (
                            <EmptyContent>
                                <Button
                                    data-test="compose-email-button"
                                    onClick={() => setComposeOpen(true)}
                                >
                                    Compose campaign
                                </Button>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Status</TableHead>
                                <TableHead>Campaign</TableHead>
                                <TableHead>Editor</TableHead>
                                <TableHead>Recipients</TableHead>
                                <TableHead>Last test</TableHead>
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
                                    data-test="email-row"
                                >
                                    <TableCell>
                                        <Badge
                                            data-test="email-status"
                                            variant={campaignStatusVariant(
                                                email.status,
                                            )}
                                        >
                                            {
                                                CAMPAIGN_STATUS_LABELS[
                                                    email.status
                                                ]
                                            }
                                            {email.progress !== null
                                                ? ` · ${email.progress}%`
                                                : null}
                                            {email.scheduled_at
                                                ? ` · ${new Date(email.scheduled_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}`
                                                : null}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex min-w-0 flex-col">
                                            <Link
                                                href={
                                                    isDraft(email.status)
                                                        ? edit([
                                                              currentTeam.slug,
                                                              email.uuid,
                                                          ])
                                                        : show([
                                                              currentTeam.slug,
                                                              email.uuid,
                                                          ])
                                                }
                                                prefetch
                                                className="font-medium underline-offset-4 hover:underline"
                                                data-test="campaign-name-link"
                                            >
                                                {email.name}
                                            </Link>
                                            <span className="text-muted-foreground">
                                                {email.subject}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {EDITOR_LABELS[email.editor]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {!email.audience ? (
                                            <span className="text-muted-foreground">
                                                Not set
                                            </span>
                                        ) : email.recipient_count === 0 ? (
                                            <span className="text-muted-foreground">
                                                No recipients
                                            </span>
                                        ) : (
                                            <AvatarGroup
                                                data-test="email-recipient-avatars"
                                                aria-label={`${email.recipient_count} recipients`}
                                            >
                                                {email.recipients.map(
                                                    (recipient) => (
                                                        <Avatar
                                                            key={recipient.uuid}
                                                            size="sm"
                                                        >
                                                            <AvatarImage
                                                                src={
                                                                    recipient.avatar
                                                                }
                                                                alt=""
                                                            />
                                                            <AvatarFallback>
                                                                {recipient.email
                                                                    .charAt(0)
                                                                    .toUpperCase()}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                    ),
                                                )}
                                                {email.recipient_count >
                                                    email.recipients.length && (
                                                    <AvatarGroupCount className="w-auto! min-w-6 px-1 text-[10px] tabular-nums">
                                                        +
                                                        {formatRecipientOverflow(
                                                            email.recipient_count -
                                                                email.recipients
                                                                    .length,
                                                        )}
                                                    </AvatarGroupCount>
                                                )}
                                            </AvatarGroup>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {email.last_tested_at
                                            ? formatRelativeTime(
                                                  email.last_tested_at,
                                              )
                                            : '—'}
                                    </TableCell>
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
                                                        data-test="email-actions-button"
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
                                                                href={
                                                                    isDraft(
                                                                        email.status,
                                                                    )
                                                                        ? edit([
                                                                              currentTeam.slug,
                                                                              email.uuid,
                                                                          ])
                                                                        : show([
                                                                              currentTeam.slug,
                                                                              email.uuid,
                                                                          ])
                                                                }
                                                                prefetch
                                                            />
                                                        }
                                                    >
                                                        <HugeiconsIcon
                                                            icon={
                                                                isDraft(
                                                                    email.status,
                                                                )
                                                                    ? Edit03Icon
                                                                    : PieChartIcon
                                                            }
                                                        />
                                                        {isDraft(email.status)
                                                            ? canManage
                                                                ? 'Edit'
                                                                : 'View'
                                                            : 'View report'}
                                                    </DropdownMenuItem>
                                                    {canManage &&
                                                        !isSending(
                                                            email.status,
                                                        ) && (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    data-test="delete-email-button"
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
                    />

                    <DeleteEmailModal
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

EmailsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
