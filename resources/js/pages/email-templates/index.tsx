import {
    Add01Icon,
    Copy01Icon,
    Delete02Icon,
    Edit03Icon,
    File01Icon,
    Layers01Icon,
    MoreHorizontalIcon,
    SourceCodeIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Reader } from '@usewaypoint/email-builder';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import CreateEmailTemplateDialog from '@/components/create-email-template-dialog';
import DeleteEmailTemplateModal from '@/components/delete-email-template-modal';
import EmailTemplatePicker from '@/components/email-template-picker';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
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
import { useListFilters } from '@/hooks/use-list-filters';
import { ROOT_BLOCK_ID, toReaderDocument } from '@/lib/email-builder';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    duplicate,
    edit,
    index as templatesIndex,
} from '@/routes/email_templates';
import type {
    EmailEditorMode,
    EmailTemplateDetail,
    EmailTemplateIndexFilters,
} from '@/types';

type Props = {
    templates: EmailTemplateDetail[];
    filters: EmailTemplateIndexFilters;
    defaultEditor: EmailEditorMode;
    canManage: boolean;
};

const EDITOR_LABELS: Record<EmailEditorMode, string> = {
    html: 'HTML',
    builder: 'EmailBuilder.js',
    plain_text: 'Plain text',
    markdown: 'Markdown',
};

const TYPE_LABELS: Record<string, string> = {
    starter: 'Starter',
    team: 'Team',
};

function ScaledEmailFrame({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-full w-full items-start justify-center overflow-hidden">
            <div className="h-[520px] w-[600px] shrink-0 origin-top scale-[0.42]">
                {children}
            </div>
        </div>
    );
}

function TemplatePreview({ template }: { template: EmailTemplateDetail }) {
    if (template.editor === 'builder' && template.design) {
        return (
            <ScaledEmailFrame>
                <Reader
                    document={toReaderDocument(template.design)}
                    rootBlockId={ROOT_BLOCK_ID}
                />
            </ScaledEmailFrame>
        );
    }

    if (template.html) {
        return (
            <ScaledEmailFrame>
                <iframe
                    title={`${template.name} preview`}
                    sandbox=""
                    srcDoc={template.html}
                    tabIndex={-1}
                    className="pointer-events-none h-full w-full border-0 bg-background"
                />
            </ScaledEmailFrame>
        );
    }

    return (
        <div className="grid h-full place-items-center text-sm text-muted-foreground">
            Empty template
        </div>
    );
}

function galleryUpdatedLabel(updatedAt: string | null): string {
    if (!updatedAt) {
        return 'Built in';
    }

    const relative = formatRelativeTime(updatedAt);

    if (relative === 'Now') {
        return 'Just now';
    }

    return `${relative} ago`;
}

export default function EmailTemplatesIndex({
    templates,
    filters,
    defaultEditor,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [templateToDelete, setTemplateToDelete] =
        useState<EmailTemplateDetail | null>(null);
    const [templateToUse, setTemplateToUse] =
        useState<EmailTemplateDetail | null>(null);
    const { visit, clear, clearAll, hasActiveFilters, searchKey } =
        useListFilters<EmailTemplateIndexFilters>({
            url: currentTeam ? templatesIndex.url(currentTeam.slug) : '',
            filters,
            empty: { q: '', editor: '', type: '' },
        });

    if (!currentTeam) {
        return null;
    }

    const matchingTemplates = templates.filter(
        (template) => template.editor === defaultEditor,
    );

    return (
        <>
            <Head title="Templates" />
            <div className="flex flex-1 flex-col gap-8">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Templates
                    </h1>
                    {canManage && (
                        <Button
                            data-test="create-template-button"
                            onClick={() => setCreateOpen(true)}
                        >
                            <HugeiconsIcon
                                icon={Add01Icon}
                                data-icon="inline-start"
                            />
                            New template
                        </Button>
                    )}
                </div>

                {(templates.length > 0 || hasActiveFilters) && (
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <FilterMenu
                                testId="template-filter-button"
                                fields={[
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
                                        testId: 'template-editor-filter',
                                    },
                                    {
                                        key: 'type',
                                        icon: Layers01Icon,
                                        label: 'Type',
                                        value: filters.type,
                                        allLabel: 'All types',
                                        options: Object.entries(
                                            TYPE_LABELS,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        })),
                                        onValueChange: (type) =>
                                            visit({ type }),
                                        testId: 'template-type-filter',
                                    },
                                ]}
                            />
                            <ListSearch
                                key={searchKey}
                                value={filters.q}
                                onSearch={(q) => visit({ q })}
                                placeholder="Search templates"
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
                                ...(filters.type !== ''
                                    ? [
                                          {
                                              key: 'type',
                                              field: 'Type',
                                              value:
                                                  TYPE_LABELS[filters.type] ??
                                                  filters.type,
                                              onClear: () =>
                                                  clear({ type: '' }),
                                          },
                                      ]
                                    : []),
                            ]}
                            onClearAll={clearAll}
                            clearTestId="clear-template-filters"
                        />
                    </div>
                )}

                {templates.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={File01Icon} />
                            </EmptyMedia>
                            <EmptyTitle>
                                {hasActiveFilters
                                    ? 'No matching templates'
                                    : 'No templates yet'}
                            </EmptyTitle>
                            <EmptyDescription>
                                {hasActiveFilters
                                    ? 'Try another search or filter.'
                                    : canManage
                                      ? 'Create a template to reuse as a campaign starting point.'
                                      : 'Templates saved by your team will appear here.'}
                            </EmptyDescription>
                        </EmptyHeader>
                        {canManage && !hasActiveFilters && (
                            <EmptyContent>
                                <Button onClick={() => setCreateOpen(true)}>
                                    New template
                                </Button>
                            </EmptyContent>
                        )}
                    </Empty>
                ) : (
                    <div className="grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                        {templates.map((template) => {
                            const canUse =
                                canManage && template.editor === defaultEditor;
                            const canEdit = canManage && !template.is_starter;

                            const preview = (
                                <>
                                    <div
                                        data-test="email-template-preview"
                                        className={cn(
                                            'h-48 w-full overflow-hidden rounded-2xl bg-muted px-4 pt-4',
                                            canUse &&
                                                'transition-shadow duration-300 ease-out group-hover:shadow-md motion-reduce:transition-none motion-reduce:group-hover:shadow-none',
                                        )}
                                    >
                                        <div className="h-[calc(100%+1.5rem)] overflow-hidden rounded-t-xl border border-b-0 bg-background">
                                            <TemplatePreview
                                                template={template}
                                            />
                                        </div>
                                    </div>
                                    <div className="flex min-w-0 flex-col gap-0.5 px-0.5">
                                        <p className="truncate font-semibold">
                                            {template.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {template.editor !== defaultEditor
                                                ? 'Switch the team editor to use this template.'
                                                : galleryUpdatedLabel(
                                                      template.updated_at,
                                                  )}
                                        </p>
                                    </div>
                                </>
                            );

                            return (
                                <div
                                    key={template.uuid}
                                    data-test="email-template-card"
                                    className={cn(
                                        'relative flex flex-col gap-3',
                                        canUse && 'group',
                                    )}
                                >
                                    {canUse ? (
                                        <button
                                            type="button"
                                            data-test="use-template-button"
                                            className="flex flex-col gap-3 text-left"
                                            onClick={() =>
                                                setTemplateToUse(template)
                                            }
                                        >
                                            {preview}
                                        </button>
                                    ) : (
                                        <div className="flex flex-col gap-3">
                                            {preview}
                                        </div>
                                    )}

                                    {canManage && (
                                        <div className="absolute top-2 right-2">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger
                                                    render={
                                                        <Button
                                                            size="icon"
                                                            variant="secondary"
                                                            data-test="template-actions-button"
                                                            aria-label={`More actions for ${template.name}`}
                                                        />
                                                    }
                                                >
                                                    <HugeiconsIcon
                                                        icon={
                                                            MoreHorizontalIcon
                                                        }
                                                    />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        {canEdit && (
                                                            <DropdownMenuItem
                                                                data-test="edit-template-button"
                                                                render={
                                                                    <Link
                                                                        href={edit(
                                                                            [
                                                                                currentTeam.slug,
                                                                                template.uuid,
                                                                            ],
                                                                        )}
                                                                        prefetch
                                                                    />
                                                                }
                                                            >
                                                                <HugeiconsIcon
                                                                    icon={
                                                                        Edit03Icon
                                                                    }
                                                                />
                                                                Edit
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuItem
                                                            data-test="duplicate-template-button"
                                                            onClick={() =>
                                                                router.post(
                                                                    duplicate.url(
                                                                        [
                                                                            currentTeam.slug,
                                                                            template.uuid,
                                                                        ],
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
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
                                                        {canEdit && (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    data-test="delete-template-button"
                                                                    onClick={() =>
                                                                        setTemplateToDelete(
                                                                            template,
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
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {canManage && (
                <>
                    <CreateEmailTemplateDialog
                        key={`create-${String(createOpen)}`}
                        teamSlug={currentTeam.slug}
                        open={createOpen}
                        onOpenChange={setCreateOpen}
                    />

                    {templateToUse && (
                        <EmailTemplatePicker
                            key={`use-${templateToUse.uuid}`}
                            teamSlug={currentTeam.slug}
                            templates={matchingTemplates}
                            defaultEditor={defaultEditor}
                            initialTemplate={templateToUse.uuid}
                            open
                            onOpenChange={(open) =>
                                setTemplateToUse(open ? templateToUse : null)
                            }
                        />
                    )}

                    <DeleteEmailTemplateModal
                        teamSlug={currentTeam.slug}
                        template={templateToDelete}
                        open={templateToDelete !== null}
                        onOpenChange={(open) =>
                            setTemplateToDelete(open ? templateToDelete : null)
                        }
                    />
                </>
            )}
        </>
    );
}

EmailTemplatesIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Templates',
            href: props.currentTeam
                ? templatesIndex(props.currentTeam.slug)
                : '/',
        },
    ],
});
