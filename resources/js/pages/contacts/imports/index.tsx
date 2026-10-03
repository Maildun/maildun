import { Add01Icon, Csv01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { ContactImportStatusBadge } from '@/components/contact-import-stages';
import { Paginator } from '@/components/paginator';
import { Button } from '@/components/ui/button';
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
import { formatRelativeTime } from '@/lib/format';
import { create, show } from '@/routes/contacts/imports';
import type { Paginated } from '@/types/audiences';
import type { ContactImport } from '@/types/contacts';

type Props = {
    imports: Paginated<ContactImport>;
    canImport: boolean;
};

export default function ContactImportsIndex({ imports, canImport }: Props) {
    const { currentTeam } = usePage().props;
    const running = imports.data.some(
        (contactImport) =>
            contactImport.status === 'pending' ||
            contactImport.status === 'processing',
    );
    const { start, stop } = usePoll(
        3000,
        { only: ['imports'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (running) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [running, start, stop]);

    if (!currentTeam) {
        return null;
    }

    const newImportButton = canImport ? (
        <Button
            nativeButton={false}
            render={<Link href={create(currentTeam.slug)} />}
        >
            <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" />
            New import
        </Button>
    ) : null;

    return (
        <>
            <Head title="Imports" />

            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Imports
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every CSV imported into this workspace, its progress
                            and its results.
                        </p>
                    </div>
                    {newImportButton}
                </div>

                {imports.total === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={Csv01Icon} />
                            </EmptyMedia>
                            <EmptyTitle>No imports yet</EmptyTitle>
                            <EmptyDescription>
                                Upload a CSV to add contacts to this workspace
                                or to an audience.
                            </EmptyDescription>
                        </EmptyHeader>
                        {newImportButton ? (
                            <EmptyContent>{newImportButton}</EmptyContent>
                        ) : null}
                    </Empty>
                ) : (
                    <Table
                        className="table-fixed"
                        footer={<Paginator paginator={imports} showSummary />}
                    >
                        <TableHeader>
                            <TableRow>
                                <TableHead>File</TableHead>
                                <TableHead className="w-48">
                                    Destination
                                </TableHead>
                                <TableHead className="w-36">Status</TableHead>
                                <TableHead className="w-56">Results</TableHead>
                                <TableHead className="w-28">Created</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {imports.data.map((contactImport) => (
                                <TableRow key={contactImport.uuid}>
                                    <TableCell className="truncate">
                                        <Link
                                            href={show([
                                                currentTeam.slug,
                                                contactImport.uuid,
                                            ])}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {contactImport.file_name}
                                        </Link>
                                        {contactImport.uploaded_by ? (
                                            <p className="truncate text-xs text-muted-foreground">
                                                by {contactImport.uploaded_by}
                                            </p>
                                        ) : null}
                                    </TableCell>
                                    <TableCell className="truncate text-muted-foreground">
                                        {contactImport.audience?.name ??
                                            'Workspace contacts'}
                                    </TableCell>
                                    <TableCell>
                                        <ContactImportStatusBadge
                                            contactImport={contactImport}
                                        />
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground tabular-nums">
                                        <ImportResultSummary
                                            contactImport={contactImport}
                                        />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {contactImport.created_at
                                            ? formatRelativeTime(
                                                  contactImport.created_at,
                                              )
                                            : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </>
    );
}

function ImportResultSummary({
    contactImport,
}: {
    contactImport: ContactImport;
}) {
    if (contactImport.status === 'draft') {
        return <>{contactImport.total_rows.toLocaleString()} rows to map</>;
    }

    if (
        contactImport.status === 'pending' ||
        contactImport.status === 'processing'
    ) {
        return (
            <>
                {contactImport.processed_rows.toLocaleString()} of{' '}
                {contactImport.total_rows.toLocaleString()} rows
            </>
        );
    }

    return (
        <>
            {contactImport.imported_contacts.toLocaleString()} new ·{' '}
            {contactImport.updated_contacts.toLocaleString()} updated ·{' '}
            <span
                className={
                    contactImport.failed_rows > 0
                        ? 'text-destructive'
                        : undefined
                }
            >
                {contactImport.failed_rows.toLocaleString()} failed
            </span>
        </>
    );
}
