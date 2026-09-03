import {
    Csv01Icon,
    MoreHorizontalIcon,
    Xls01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
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
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import type { ContactImport } from '@/types/contacts';

export function ContactImportDialog({
    action,
    imports,
    audienceName,
    pollOnly,
    exportCsvUrl,
    exportXlsUrl,
}: {
    action: string;
    imports: ContactImport[];
    audienceName?: string;
    pollOnly: string[];
    exportCsvUrl: string;
    exportXlsUrl: string;
}) {
    const [open, setOpen] = useState(false);
    const active = imports.some(
        (contactImport) =>
            contactImport.status === 'pending' ||
            contactImport.status === 'processing',
    );
    const { start, stop } = usePoll(
        2000,
        {
            only: pollOnly,
        },
        { autoStart: false },
    );

    useEffect(() => {
        if (active) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [active, start, stop]);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Import or export contacts"
                        />
                    }
                >
                    <HugeiconsIcon icon={MoreHorizontalIcon} />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuLabel>Import</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => setOpen(true)}>
                            <HugeiconsIcon icon={Csv01Icon} />
                            CSV
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuLabel>Export</DropdownMenuLabel>
                        <DropdownMenuItem render={<a href={exportCsvUrl} />}>
                            <HugeiconsIcon icon={Csv01Icon} />
                            CSV
                        </DropdownMenuItem>
                        <DropdownMenuItem render={<a href={exportXlsUrl} />}>
                            <HugeiconsIcon icon={Xls01Icon} />
                            XLS
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:w-lg">
                    <Form
                        action={action}
                        method="post"
                        encType="multipart/form-data"
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ errors, processing, progress }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Import{' '}
                                        {audienceName
                                            ? 'contacts to audience'
                                            : 'contacts'}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Upload a CSV with an email column. First
                                        name and last name columns are optional.
                                        Existing
                                        {audienceName
                                            ? ` contacts in ${audienceName}`
                                            : ' workspace contacts'}{' '}
                                        are skipped as duplicates.
                                    </DialogDescription>
                                </DialogHeader>

                                <Field>
                                    <FieldLabel htmlFor="contact-import-file">
                                        CSV file
                                    </FieldLabel>
                                    <label
                                        htmlFor="contact-import-file"
                                        className="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed p-4 transition-colors hover:bg-muted/50"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                            <HugeiconsIcon icon={Csv01Icon} />
                                        </span>
                                        <span className="flex min-w-0 flex-col gap-0.5">
                                            <span className="font-medium">
                                                Choose a CSV file
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                Up to 10 MB · email, first_name,
                                                last_name
                                            </span>
                                        </span>
                                    </label>
                                    <input
                                        id="contact-import-file"
                                        name="file"
                                        type="file"
                                        accept=".csv,text/csv,text/plain"
                                        className="sr-only"
                                        required
                                    />
                                    <FieldError>{errors.file}</FieldError>
                                </Field>

                                {progress ? (
                                    <p className="text-sm text-muted-foreground">
                                        Uploading {progress.percentage}%
                                    </p>
                                ) : null}

                                {imports.length > 0 ? (
                                    <div className="flex flex-col gap-3 border-t pt-5">
                                        <p className="text-sm font-medium">
                                            Recent imports
                                        </p>
                                        <div className="flex max-h-52 flex-col gap-2 overflow-y-auto">
                                            {imports.map((contactImport) => (
                                                <ImportStatus
                                                    key={contactImport.uuid}
                                                    contactImport={
                                                        contactImport
                                                    }
                                                    audienceImport={Boolean(
                                                        audienceName,
                                                    )}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ) : null}

                                <DialogFooter showCloseButton>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : null}
                                        Import
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function ImportStatus({
    contactImport,
    audienceImport,
}: {
    contactImport: ContactImport;
    audienceImport: boolean;
}) {
    const running =
        contactImport.status === 'pending' ||
        contactImport.status === 'processing';

    return (
        <div className="flex flex-col gap-2 rounded-lg border p-3">
            <div className="flex items-center justify-between gap-3">
                <span className="truncate text-sm font-medium">
                    {contactImport.file_name}
                </span>
                <Badge
                    variant={
                        contactImport.status === 'failed'
                            ? 'destructive'
                            : contactImport.status === 'completed'
                              ? 'success'
                              : 'secondary'
                    }
                >
                    {running ? <Spinner /> : null}
                    {contactImport.status === 'pending'
                        ? 'Queued'
                        : contactImport.status === 'processing'
                          ? 'Processing'
                          : contactImport.status === 'completed'
                            ? 'Completed'
                            : 'Failed'}
                </Badge>
            </div>
            {running ? (
                <p className="text-xs text-muted-foreground">
                    {contactImport.processed_rows > 0
                        ? `${contactImport.processed_rows} rows processed`
                        : 'Waiting for a queue worker'}
                </p>
            ) : (
                <p className="text-xs text-muted-foreground">
                    {contactImport.imported_contacts} new contacts
                    {audienceImport
                        ? ` · ${contactImport.imported_subscribers} added to audience`
                        : ''}{' '}
                    · {contactImport.duplicate_rows} duplicates ·{' '}
                    {contactImport.failed_rows} failed
                </p>
            )}
            {contactImport.errors[0] ? (
                <p className="text-xs text-destructive">
                    {contactImport.errors[0]}
                </p>
            ) : null}
        </div>
    );
}
