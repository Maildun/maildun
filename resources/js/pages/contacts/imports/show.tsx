import {
    Alert02Icon,
    Download04Icon,
    InformationCircleIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link, useForm, usePage, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import {
    ContactImportStageList,
    ContactImportStatusBadge,
} from '@/components/contact-import-stages';
import { SettingsPanel } from '@/components/settings-panel';
import { TagsCombobox } from '@/components/tags-combobox';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { AnimatedCounter } from '@/components/ui/animated-counter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Callout } from '@/components/ui/callout';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as showAudience } from '@/routes/audiences';
import {
    cancel,
    destroy,
    report as downloadReport,
    retry,
    start,
} from '@/routes/contacts/imports';
import type {
    ContactImport,
    ContactImportIssue,
    ContactImportIssueAction,
    ContactImportIssueOption,
    ContactImportMapping,
    ContactImportReviewFlag,
    ContactImportRowError,
} from '@/types/contacts';

type Props = {
    contactImport: ContactImport;
    rowErrors: ContactImportRowError[];
    reviewFlags: ContactImportReviewFlag[];
    issues: ContactImportIssueOption[];
    mapping: ContactImportMapping | null;
    canRetry: boolean;
    canManage: boolean;
};

type RouteArgs = [string, string];

const IGNORE = '__ignore';

const ACTION_LABELS: Record<ContactImportIssueAction, string> = {
    fix: 'Fix it',
    import: 'Import and flag',
    skip: 'Skip the row',
};

const ISSUE_DESCRIPTIONS: Record<ContactImportIssue, string> = {
    typo: 'Misspelled providers such as gmial.com or gmail.con. Fixes apply only when the typed domain cannot receive mail.',
    disposable:
        'Throwaway inboxes such as mailinator.com that rarely belong to real subscribers.',
    undeliverable: 'Domains with no mail server, so every send would bounce.',
    role_address:
        'Shared inboxes such as info@ or support@ that often ignore or report marketing.',
    duplicate_in_file: 'The same address on more than one row.',
    suppressed:
        'Addresses that bounced or complained before in this workspace.',
};

export default function ContactImportShow({
    contactImport,
    rowErrors,
    reviewFlags,
    issues,
    mapping,
    canRetry,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const running =
        contactImport.status === 'pending' ||
        contactImport.status === 'processing';
    const { start: startPolling, stop } = usePoll(
        2000,
        { only: ['contactImport', 'rowErrors', 'reviewFlags', 'canRetry'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (running) {
            startPolling();
        } else {
            stop();
        }

        return stop;
    }, [running, startPolling, stop]);

    if (!currentTeam) {
        return null;
    }

    const routeArgs: RouteArgs = [currentTeam.slug, contactImport.uuid];
    const destination = contactImport.audience ? (
        <Link
            href={showAudience([currentTeam.slug, contactImport.audience.uuid])}
            className="font-medium text-foreground underline-offset-4 hover:underline"
        >
            {contactImport.audience.name}
        </Link>
    ) : (
        'workspace contacts'
    );

    return (
        <>
            <Head title={`Import · ${contactImport.file_name}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
                <div className="flex flex-col gap-1">
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <h1 className="truncate text-2xl font-semibold tracking-tight">
                            {contactImport.file_name}
                        </h1>
                        <ContactImportStatusBadge
                            contactImport={contactImport}
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {contactImport.total_rows.toLocaleString()} rows into{' '}
                        {destination}
                        {contactImport.uploaded_by
                            ? ` · uploaded by ${contactImport.uploaded_by}`
                            : ''}
                        {contactImport.created_at
                            ? ` ${formatRelativeTime(contactImport.created_at)}`
                            : ''}
                    </p>
                </div>

                <Outcome
                    contactImport={contactImport}
                    routeArgs={routeArgs}
                    canRetry={canRetry && canManage}
                />

                {mapping && canManage ? (
                    <MappingForm
                        contactImport={contactImport}
                        mapping={mapping}
                        issues={issues}
                        routeArgs={routeArgs}
                    />
                ) : null}

                {mapping && !canManage ? (
                    <Callout icon={InformationCircleIcon}>
                        This import is waiting for someone who can manage
                        contacts to map its columns.
                    </Callout>
                ) : null}

                {contactImport.status !== 'draft' ? (
                    <ResultPanel
                        contactImport={contactImport}
                        routeArgs={routeArgs}
                    />
                ) : null}

                {reviewFlags.length > 0 ? (
                    <ReviewFlagsPanel
                        contactImport={contactImport}
                        reviewFlags={reviewFlags}
                        issues={issues}
                    />
                ) : null}

                {rowErrors.length > 0 ? (
                    <RowErrorsPanel
                        contactImport={contactImport}
                        rowErrors={rowErrors}
                    />
                ) : null}

                <SettingsPanel
                    variant="inset"
                    title="Progress"
                    description="The import runs on the server. You can close this page; it keeps going."
                    actions={
                        canManage ? (
                            running ? (
                                <CancelImport routeArgs={routeArgs} />
                            ) : (
                                <DeleteImport
                                    routeArgs={routeArgs}
                                    isDraft={contactImport.status === 'draft'}
                                />
                            )
                        ) : null
                    }
                >
                    <ContactImportStageList contactImport={contactImport} />
                </SettingsPanel>
            </div>
        </>
    );
}

function Outcome({
    contactImport,
    routeArgs,
    canRetry,
}: {
    contactImport: ContactImport;
    routeArgs: RouteArgs;
    canRetry: boolean;
}) {
    if (contactImport.status === 'failed') {
        return (
            <Callout
                variant="danger"
                icon={Alert02Icon}
                data-test="import-failed"
            >
                <div className="flex flex-col items-start gap-3">
                    <div className="flex flex-col gap-1">
                        <p className="font-medium">The import stopped</p>
                        <p className="break-words whitespace-pre-line">
                            {contactImport.failure_message ??
                                'The import did not finish.'}
                        </p>
                    </div>
                    {canRetry ? (
                        <Form {...retry.form(routeArgs)}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    data-test="import-retry"
                                >
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : null}
                                    Retry from where it stopped
                                </Button>
                            )}
                        </Form>
                    ) : null}
                </div>
            </Callout>
        );
    }

    if (contactImport.status === 'cancelled') {
        return (
            <Callout icon={InformationCircleIcon}>
                Import cancelled. Contacts imported before cancelling are kept.
                Upload the file again to import the rest.
            </Callout>
        );
    }

    if (contactImport.status === 'completed') {
        return (
            <Callout variant="success" icon={InformationCircleIcon}>
                Import finished{' '}
                {contactImport.completed_at
                    ? formatRelativeTime(contactImport.completed_at)
                    : ''}
                .
                {contactImport.failed_rows > 0
                    ? ` ${contactImport.failed_rows.toLocaleString()} rows could not be imported; download them below, fix them and import again.`
                    : ''}
            </Callout>
        );
    }

    return null;
}

function MappingForm({
    contactImport,
    mapping,
    issues,
    routeArgs,
}: {
    contactImport: ContactImport;
    mapping: ContactImportMapping;
    issues: ContactImportIssueOption[];
    routeArgs: RouteArgs;
}) {
    const isAudienceImport = contactImport.audience !== null;
    const form = useForm({
        column_map: mapping.columnMap,
        merge_strategy: 'skip' as ContactImport['merge_strategy'],
        tags: [] as string[],
        resubscribe_unsubscribed: false,
        consent_confirmed: false,
        review_options: contactImport.review_options,
    });
    const fieldLabels = Object.fromEntries(
        mapping.fields.map((field) => [field.value, field.label]),
    );
    const mappedColumns = form.data.column_map
        .map((field, index) => ({ field, index }))
        .filter(
            (column): column is { field: string; index: number } =>
                column.field !== null,
        );
    const hasEmail = form.data.column_map.includes('email');

    const setColumnField = (columnIndex: number, field: string | null) => {
        form.setData(
            'column_map',
            form.data.column_map.map((current, index) => {
                if (index === columnIndex) {
                    return field;
                }

                return field !== null && current === field ? null : current;
            }),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(start.url(routeArgs), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <SettingsPanel
                variant="inset"
                title="Map columns"
                description="Choose what each column holds. Columns set to Don't import are skipped."
            >
                <Table className="table-fixed">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-1/3">CSV column</TableHead>
                            <TableHead>Sample values</TableHead>
                            <TableHead className="w-56">Import as</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {mapping.headers.map((header, columnIndex) => {
                            const samples = mapping.sampleRows
                                .map((row) => row[columnIndex] ?? '')
                                .filter((value) => value !== '')
                                .slice(0, 3);
                            const field = form.data.column_map[columnIndex];

                            return (
                                <TableRow key={`${header}-${columnIndex}`}>
                                    <TableCell className="truncate font-medium">
                                        {header || `Column ${columnIndex + 1}`}
                                    </TableCell>
                                    <TableCell className="truncate text-muted-foreground">
                                        {samples.length > 0
                                            ? samples.join(', ')
                                            : '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Select
                                            items={[
                                                {
                                                    value: IGNORE,
                                                    label: "Don't import",
                                                },
                                                ...mapping.fields,
                                            ]}
                                            value={field ?? IGNORE}
                                            onValueChange={(value) =>
                                                setColumnField(
                                                    columnIndex,
                                                    typeof value === 'string' &&
                                                        value !== IGNORE
                                                        ? value
                                                        : null,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                className={cn(
                                                    'w-full',
                                                    field === null &&
                                                        'text-muted-foreground',
                                                )}
                                                aria-label={`Import ${header} as`}
                                                data-test={`import-map-${columnIndex}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value={IGNORE}>
                                                        Don&apos;t import
                                                    </SelectItem>
                                                </SelectGroup>
                                                <SelectSeparator />
                                                <SelectGroup>
                                                    {mapping.fields.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
                {!hasEmail || form.errors.column_map ? (
                    <p className="border-t px-6 py-3 text-sm text-destructive sm:px-7">
                        {form.errors.column_map ??
                            'Choose which column holds the email address.'}
                    </p>
                ) : null}
            </SettingsPanel>

            {mappedColumns.length > 0 && mapping.sampleRows.length > 0 ? (
                <SettingsPanel
                    variant="inset"
                    title="Preview"
                    description={`The first ${mapping.sampleRows.length} rows as they will be imported.`}
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {mappedColumns.map((column) => (
                                    <TableHead key={column.field}>
                                        {fieldLabels[column.field] ??
                                            column.field}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {mapping.sampleRows.map((row, rowIndex) => (
                                <TableRow key={rowIndex}>
                                    {mappedColumns.map((column) => (
                                        <TableCell
                                            key={column.field}
                                            className="max-w-48 truncate"
                                        >
                                            {row[column.index] || (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </SettingsPanel>
            ) : null}

            <SettingsPanel
                variant="inset"
                title="Options"
                description="Decide what happens to people who are already in this workspace."
            >
                <FieldGroup className="gap-6 p-6 sm:p-7">
                    <Field>
                        <FieldLabel>Existing contacts</FieldLabel>
                        <div
                            role="radiogroup"
                            aria-label="Existing contacts"
                            className="grid gap-3 sm:grid-cols-2"
                        >
                            <MergeOption
                                selected={form.data.merge_strategy === 'skip'}
                                onSelect={() =>
                                    form.setData('merge_strategy', 'skip')
                                }
                                title="Leave them unchanged"
                                description="Existing names, tags and fields stay exactly as they are."
                                testId="import-merge-skip"
                            />
                            <MergeOption
                                selected={
                                    form.data.merge_strategy === 'fill_blanks'
                                }
                                onSelect={() =>
                                    form.setData(
                                        'merge_strategy',
                                        'fill_blanks',
                                    )
                                }
                                title="Fill empty fields"
                                description="Add missing names and field values, and add tags. Existing values are never overwritten."
                                testId="import-merge-fill"
                            />
                        </div>
                        <FieldError>{form.errors.merge_strategy}</FieldError>
                    </Field>

                    <Field data-invalid={Boolean(form.errors.tags)}>
                        <FieldLabel htmlFor="import-tags">
                            Tag every imported contact
                        </FieldLabel>
                        <TagsCombobox
                            id="import-tags"
                            value={form.data.tags}
                            onValueChange={(names) =>
                                form.setData('tags', names)
                            }
                            availableTags={mapping.tags}
                            placeholder="Search or create a tag"
                        />
                        <FieldDescription>
                            Useful to find this batch later, such as
                            &ldquo;Webinar 2026&rdquo;.
                        </FieldDescription>
                        <FieldError>{form.errors.tags}</FieldError>
                    </Field>

                    {isAudienceImport ? (
                        <>
                            <Field orientation="horizontal">
                                <FieldContent>
                                    <FieldLabel htmlFor="import-resubscribe">
                                        Resubscribe people who unsubscribed
                                    </FieldLabel>
                                    <FieldDescription>
                                        Off by default. Only turn this on if
                                        these people opted in again.
                                    </FieldDescription>
                                </FieldContent>
                                <Switch
                                    id="import-resubscribe"
                                    checked={form.data.resubscribe_unsubscribed}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'resubscribe_unsubscribed',
                                            checked,
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                orientation="horizontal"
                                data-invalid={Boolean(
                                    form.errors.consent_confirmed,
                                )}
                            >
                                <Checkbox
                                    id="import-consent"
                                    checked={form.data.consent_confirmed}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'consent_confirmed',
                                            checked === true,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.consent_confirmed,
                                    )}
                                    data-test="import-consent"
                                />
                                <div className="flex flex-col gap-1">
                                    <FieldLabel
                                        htmlFor="import-consent"
                                        className="font-normal"
                                    >
                                        These people agreed to receive emails
                                        from {contactImport.audience?.name}
                                    </FieldLabel>
                                    <FieldError>
                                        {form.errors.consent_confirmed}
                                    </FieldError>
                                </div>
                            </Field>
                        </>
                    ) : null}
                </FieldGroup>
            </SettingsPanel>

            <SettingsPanel
                variant="inset"
                title="Email checks"
                description="Run on this server before each row is imported. Nothing is sent to another service."
            >
                <FieldGroup className="gap-5 p-6 sm:p-7">
                    {issues
                        .filter((issue) => issue.actions.length > 0)
                        .map((issue) => (
                            <Field
                                key={issue.value}
                                orientation="horizontal"
                                data-invalid={Boolean(
                                    form.errors[
                                        `review_options.${issue.value}` as keyof typeof form.errors
                                    ],
                                )}
                            >
                                <FieldContent>
                                    <FieldLabel
                                        htmlFor={`import-review-${issue.value}`}
                                    >
                                        {issue.label}
                                    </FieldLabel>
                                    <FieldDescription>
                                        {ISSUE_DESCRIPTIONS[issue.value]}
                                    </FieldDescription>
                                </FieldContent>
                                <Select
                                    items={issue.actions.map((action) => ({
                                        value: action,
                                        label: ACTION_LABELS[action],
                                    }))}
                                    value={
                                        form.data.review_options[issue.value] ??
                                        issue.actions[0]
                                    }
                                    onValueChange={(value) =>
                                        form.setData('review_options', {
                                            ...form.data.review_options,
                                            [issue.value]:
                                                value as ContactImportIssueAction,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id={`import-review-${issue.value}`}
                                        className="w-48 shrink-0"
                                        data-test={`import-review-${issue.value}`}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {issue.actions.map((action) => (
                                                <SelectItem
                                                    key={action}
                                                    value={action}
                                                >
                                                    {ACTION_LABELS[action]}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        ))}
                    <p className="text-xs text-muted-foreground">
                        Rows repeated in the file and addresses that bounced or
                        complained before are always flagged in the report.
                    </p>
                </FieldGroup>
            </SettingsPanel>

            <div className="flex justify-end gap-2">
                <Button
                    type="submit"
                    disabled={!hasEmail || form.processing}
                    data-test="import-start"
                >
                    {form.processing ? (
                        <Spinner data-icon="inline-start" />
                    ) : null}
                    Import {contactImport.total_rows.toLocaleString()} rows
                </Button>
            </div>
        </form>
    );
}

function MergeOption({
    selected,
    onSelect,
    title,
    description,
    testId,
}: {
    selected: boolean;
    onSelect: () => void;
    title: string;
    description: string;
    testId: string;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={onSelect}
            data-test={testId}
            className={cn(
                'flex gap-3 rounded-lg border p-4 text-left transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-hidden',
                selected && 'border-primary bg-muted/40 ring-1 ring-primary',
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border',
                    selected && 'border-primary',
                )}
            >
                {selected ? (
                    <span className="size-2 rounded-full bg-primary" />
                ) : null}
            </span>
            <span className="flex flex-col gap-1">
                <span className="text-sm font-medium">{title}</span>
                <span className="text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </button>
    );
}

function ResultPanel({
    contactImport,
    routeArgs,
}: {
    contactImport: ContactImport;
    routeArgs: RouteArgs;
}) {
    const hasReport =
        contactImport.error_count > 0 ||
        Object.keys(contactImport.review_counts).length > 0;

    const facts: { label: string; value: number; tone?: string }[] = [
        { label: 'New contacts', value: contactImport.imported_contacts },
        { label: 'Updated', value: contactImport.updated_contacts },
        ...(contactImport.audience
            ? [
                  {
                      label: 'Added to audience',
                      value: contactImport.imported_subscribers,
                  },
                  {
                      label: 'Kept unsubscribed',
                      value: contactImport.skipped_unsubscribed,
                  },
              ]
            : []),
        { label: 'Unchanged', value: contactImport.duplicate_rows },
        {
            label: 'Flagged',
            value: contactImport.flagged_rows,
            tone:
                contactImport.flagged_rows > 0
                    ? 'text-amber-600 dark:text-amber-400'
                    : undefined,
        },
        { label: 'Skipped by checks', value: contactImport.skipped_rows },
        {
            label: 'Failed',
            value: contactImport.failed_rows,
            tone:
                contactImport.failed_rows > 0 ? 'text-destructive' : undefined,
        },
    ];

    return (
        <SettingsPanel
            variant="inset"
            title={contactImport.status === 'completed' ? 'Results' : 'So far'}
            description={
                contactImport.merge_strategy === 'fill_blanks'
                    ? 'Existing contacts had their empty fields filled.'
                    : 'Existing contacts were left unchanged.'
            }
            actions={
                hasReport ? (
                    <Button
                        variant="outline"
                        size="sm"
                        nativeButton={false}
                        render={<a href={downloadReport.url(routeArgs)} />}
                        data-test="import-report-download"
                    >
                        <HugeiconsIcon
                            icon={Download04Icon}
                            data-icon="inline-start"
                        />
                        Download report
                    </Button>
                ) : null
            }
        >
            <dl className="grid grid-cols-2 gap-x-6 gap-y-4 px-6 py-5 text-sm sm:grid-cols-4 sm:px-7">
                {facts.map((fact) => (
                    <div key={fact.label} className="flex flex-col gap-0.5">
                        <dt className="text-xs text-muted-foreground">
                            {fact.label}
                        </dt>
                        <dd
                            className={cn(
                                'text-lg font-medium tabular-nums',
                                fact.tone,
                            )}
                        >
                            <AnimatedCounter value={fact.value} />
                        </dd>
                    </div>
                ))}
            </dl>
            {contactImport.tag_names.length > 0 ? (
                <p className="border-t px-6 py-3 text-xs text-muted-foreground sm:px-7">
                    Tagged with {contactImport.tag_names.join(', ')}
                </p>
            ) : null}
        </SettingsPanel>
    );
}

function ReviewFlagsPanel({
    contactImport,
    reviewFlags,
    issues,
}: {
    contactImport: ContactImport;
    reviewFlags: ContactImportReviewFlag[];
    issues: ContactImportIssueOption[];
}) {
    const issueLabels = Object.fromEntries(
        issues.map((issue) => [issue.value, issue.label]),
    ) as Record<ContactImportIssue, string>;
    const total = Object.values(contactImport.review_counts).reduce(
        (sum, count) => sum + (count ?? 0),
        0,
    );

    return (
        <SettingsPanel
            variant="inset"
            title="Flagged rows"
            description={
                total > reviewFlags.length
                    ? `Showing ${reviewFlags.length} of ${total.toLocaleString()} flags. Download the report for the rest.`
                    : 'Addresses the email checks found. Each row shows what the import did.'
            }
        >
            <div className="flex flex-wrap gap-2 border-b px-6 py-3 sm:px-7">
                {issues
                    .filter((issue) => contactImport.review_counts[issue.value])
                    .map((issue) => (
                        <Badge key={issue.value} variant="amber">
                            {issue.label} ·{' '}
                            {contactImport.review_counts[
                                issue.value
                            ]?.toLocaleString()}
                        </Badge>
                    ))}
            </div>
            <div className="max-h-96 overflow-y-auto">
                <Table className="table-fixed">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-20">Row</TableHead>
                            <TableHead className="w-1/3">Email</TableHead>
                            <TableHead className="w-48">Check</TableHead>
                            <TableHead>Outcome</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {reviewFlags.map((flag, index) => (
                            <TableRow
                                key={`${flag.row}-${flag.issue}-${index}`}
                            >
                                <TableCell className="text-muted-foreground tabular-nums">
                                    {flag.row}
                                </TableCell>
                                <TableCell className="truncate">
                                    {flag.email}
                                </TableCell>
                                <TableCell className="truncate">
                                    {issueLabels[flag.issue] ?? flag.issue}
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'text-muted-foreground',
                                        flag.action === 'skipped' &&
                                            'text-destructive',
                                    )}
                                >
                                    {flagOutcome(
                                        flag,
                                        contactImport.merge_strategy,
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </SettingsPanel>
    );
}

function flagOutcome(
    flag: ContactImportReviewFlag,
    mergeStrategy: ContactImport['merge_strategy'],
): string {
    if (flag.action === 'fixed') {
        return `Fixed to ${flag.detail}`;
    }

    if (flag.action === 'skipped') {
        return 'Skipped';
    }

    switch (flag.issue) {
        case 'typo':
            return `Imported as written · did you mean ${flag.detail}?`;
        case 'duplicate_in_file':
            return mergeStrategy === 'fill_blanks'
                ? `Merged into row ${flag.detail}`
                : `Same contact as row ${flag.detail}`;
        case 'suppressed':
            return 'Imported · stays suppressed from sends';
        default:
            return 'Imported';
    }
}

function RowErrorsPanel({
    contactImport,
    rowErrors,
}: {
    contactImport: ContactImport;
    rowErrors: ContactImportRowError[];
}) {
    return (
        <SettingsPanel
            variant="inset"
            title="Rows that could not be imported"
            description={
                contactImport.error_count > rowErrors.length
                    ? `Showing ${rowErrors.length} of ${contactImport.error_count.toLocaleString()} recorded errors.`
                    : 'Fix these rows in your spreadsheet and import them again.'
            }
        >
            <div className="max-h-96 overflow-y-auto">
                <Table className="table-fixed">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-20">Row</TableHead>
                            <TableHead className="w-1/3">Email</TableHead>
                            <TableHead>Problem</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rowErrors.map((error, index) => (
                            <TableRow key={`${error.row}-${index}`}>
                                <TableCell className="text-muted-foreground tabular-nums">
                                    {error.row ?? '—'}
                                </TableCell>
                                <TableCell className="truncate">
                                    {error.email ?? '—'}
                                </TableCell>
                                <TableCell className="text-destructive">
                                    {error.message}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </SettingsPanel>
    );
}

function CancelImport({ routeArgs }: { routeArgs: RouteArgs }) {
    const [open, setOpen] = useState(false);

    return (
        <ConfirmAction
            open={open}
            onOpenChange={setOpen}
            trigger="Cancel import"
            title="Cancel the import?"
            description="The import stops after the current row. Contacts already imported are kept."
            keepLabel="Keep going"
            confirmLabel="Cancel import"
            testId="import-cancel"
        >
            {(footer) => (
                <Form
                    {...cancel.form(routeArgs)}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => footer(processing)}
                </Form>
            )}
        </ConfirmAction>
    );
}

function DeleteImport({
    routeArgs,
    isDraft,
}: {
    routeArgs: RouteArgs;
    isDraft: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <ConfirmAction
            open={open}
            onOpenChange={setOpen}
            trigger={isDraft ? 'Discard' : 'Remove from history'}
            title={isDraft ? 'Discard this import?' : 'Remove this import?'}
            description={
                isDraft
                    ? 'The uploaded file is deleted and nothing is imported.'
                    : 'Only the import record is removed. Imported contacts stay in the workspace.'
            }
            keepLabel="Keep"
            confirmLabel={isDraft ? 'Discard' : 'Remove'}
            testId="import-delete"
        >
            {(footer) => (
                <Form {...destroy.form(routeArgs)}>
                    {({ processing }) => footer(processing)}
                </Form>
            )}
        </ConfirmAction>
    );
}

function ConfirmAction({
    open,
    onOpenChange,
    trigger,
    title,
    description,
    keepLabel,
    confirmLabel,
    testId,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    trigger: string;
    title: string;
    description: string;
    keepLabel: string;
    confirmLabel: string;
    testId: string;
    children: (footer: (processing: boolean) => ReactNode) => ReactNode;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogTrigger
                render={<Button variant="outline" size="sm" />}
                data-test={testId}
            >
                {trigger}
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {children((processing) => (
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing}>
                            {keepLabel}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            {processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : null}
                            {confirmLabel}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                ))}
            </AlertDialogContent>
        </AlertDialog>
    );
}
