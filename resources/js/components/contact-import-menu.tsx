import {
    Csv01Icon,
    Clock01Icon,
    MoreHorizontalIcon,
    Xls01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    create as createContactImport,
    index as contactImports,
} from '@/routes/contacts/imports';

export function ContactImportMenu({
    teamSlug,
    audienceUuid,
    exportCsvUrl,
    exportXlsUrl,
}: {
    teamSlug: string;
    audienceUuid?: string;
    exportCsvUrl: string;
    exportXlsUrl: string;
}) {
    return (
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
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>Import</DropdownMenuLabel>
                    <DropdownMenuItem
                        render={
                            <Link
                                href={createContactImport(teamSlug, {
                                    query: audienceUuid
                                        ? { audience: audienceUuid }
                                        : undefined,
                                })}
                            />
                        }
                    >
                        <HugeiconsIcon icon={Csv01Icon} />
                        CSV
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        render={<Link href={contactImports(teamSlug)} />}
                    >
                        <HugeiconsIcon icon={Clock01Icon} />
                        Import history
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
    );
}
