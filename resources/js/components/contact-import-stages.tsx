import {
    Alert02Icon,
    CheckmarkCircle02Icon,
    Edit03Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { ReactNode } from 'react';
import { AnimatedCounter } from '@/components/ui/animated-counter';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ContactImport } from '@/types/contacts';

type StageState = 'done' | 'current' | 'action' | 'stopped' | 'upcoming';

type ImportStage = {
    key: 'upload' | 'map' | 'queue' | 'import' | 'finish';
    title: string;
    description: string;
};

const importStages: ImportStage[] = [
    {
        key: 'upload',
        title: 'Upload the file',
        description: 'The CSV is stored privately for this import.',
    },
    {
        key: 'map',
        title: 'Map columns',
        description: 'Match each CSV column to a contact field.',
    },
    {
        key: 'queue',
        title: 'Wait for a worker',
        description: 'The import runs in the background queue.',
    },
    {
        key: 'import',
        title: 'Import contacts',
        description: 'Rows are checked and saved in small batches.',
    },
    {
        key: 'finish',
        title: 'Finish up',
        description: 'Results are tallied and the uploaded file is removed.',
    },
];

function reachedStage(contactImport: ContactImport): number {
    switch (contactImport.status) {
        case 'draft':
            return 1;
        case 'pending':
            return contactImport.started_at ? 3 : 2;
        case 'processing':
            return 3;
        case 'completed':
            return importStages.length;
        default:
            return contactImport.started_at ? 3 : 2;
    }
}

export function ContactImportStatusBadge({
    contactImport,
}: {
    contactImport: Pick<ContactImport, 'status' | 'status_label'>;
}) {
    const variant =
        contactImport.status === 'completed'
            ? 'success'
            : contactImport.status === 'failed'
              ? 'destructive'
              : contactImport.status === 'cancelled'
                ? 'gray'
                : contactImport.status === 'draft'
                  ? 'outline'
                  : 'info';

    return (
        <Badge variant={variant}>
            {contactImport.status === 'pending' ||
            contactImport.status === 'processing' ? (
                <Spinner />
            ) : null}
            {contactImport.status_label}
        </Badge>
    );
}

export function ContactImportStageList({
    contactImport,
}: {
    contactImport: ContactImport;
}) {
    const reached = reachedStage(contactImport);
    const stopped =
        contactImport.status === 'failed' ||
        contactImport.status === 'cancelled';

    return (
        <ol className="flex flex-col divide-y" data-test="import-stages">
            {importStages.map((stage, index) => {
                const state: StageState =
                    index < reached
                        ? 'done'
                        : index === reached
                          ? stopped
                              ? 'stopped'
                              : contactImport.status === 'draft'
                                ? 'action'
                                : 'current'
                          : 'upcoming';

                return (
                    <StageItem
                        key={stage.key}
                        state={state}
                        title={stage.title}
                        description={stageDescription(
                            stage,
                            state,
                            contactImport,
                        )}
                    >
                        {stage.key === 'import' &&
                        (state === 'current' || state === 'stopped') ? (
                            <StageProgress
                                done={contactImport.processed_rows}
                                total={contactImport.total_rows}
                            />
                        ) : null}
                        {state === 'current' && contactImport.updated_at ? (
                            <span className="text-xs text-muted-foreground">
                                Last update{' '}
                                {formatRelativeTime(contactImport.updated_at)}
                            </span>
                        ) : null}
                    </StageItem>
                );
            })}
        </ol>
    );
}

function stageDescription(
    stage: ImportStage,
    state: StageState,
    contactImport: ContactImport,
): ReactNode {
    if (stage.key === 'upload' && state === 'done') {
        return `${contactImport.total_rows.toLocaleString()} rows found in ${contactImport.file_name}.`;
    }

    if (stage.key === 'queue' && state === 'current') {
        return 'Queued. If this does not change within a minute, check that a queue worker is running.';
    }

    if (stage.key === 'import' && state === 'stopped') {
        return contactImport.status === 'cancelled'
            ? 'Stopped. Contacts imported before cancelling are kept.'
            : 'Stopped. Rows already imported are kept, and a retry resumes from the next row.';
    }

    return stage.description;
}

function StageItem({
    state,
    title,
    description,
    children,
}: {
    state: StageState;
    title: string;
    description: ReactNode;
    children?: ReactNode;
}) {
    return (
        <li data-state={state} className="flex gap-4 px-6 py-4 sm:px-7">
            <StageIcon state={state} />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <span
                    className={cn(
                        'text-sm font-medium',
                        state === 'upcoming' && 'text-muted-foreground',
                    )}
                >
                    {title}
                </span>
                <span className="text-xs text-muted-foreground">
                    {description}
                </span>
                {children}
            </div>
        </li>
    );
}

function StageIcon({ state }: { state: StageState }) {
    if (state === 'done') {
        return (
            <HugeiconsIcon
                icon={CheckmarkCircle02Icon}
                className="mt-0.5 size-5 shrink-0 text-success"
                aria-label="Done"
            />
        );
    }

    if (state === 'current') {
        return <Spinner className="mt-0.5 size-5 shrink-0 text-info" />;
    }

    if (state === 'action') {
        return (
            <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-info/15 text-info">
                <HugeiconsIcon
                    icon={Edit03Icon}
                    className="size-3"
                    aria-label="Needs your input"
                />
            </span>
        );
    }

    if (state === 'stopped') {
        return (
            <HugeiconsIcon
                icon={Alert02Icon}
                className="mt-0.5 size-5 shrink-0 text-destructive"
                aria-label="Stopped"
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className="mt-0.5 size-5 shrink-0 rounded-full border-2 border-dashed border-muted-foreground/40"
        />
    );
}

function StageProgress({ done, total }: { done: number; total: number }) {
    if (!total) {
        return null;
    }

    return (
        <div className="flex max-w-md flex-col gap-1.5 pt-1">
            <Progress
                value={Math.min(100, (done / total) * 100)}
                aria-label="Import progress"
            />
            <span className="text-xs text-muted-foreground tabular-nums">
                <AnimatedCounter value={done} /> of{' '}
                <AnimatedCounter value={total} /> rows ·{' '}
                <AnimatedCounter
                    value={Math.min(100, Math.floor((done / total) * 100))}
                    suffix="%"
                />
            </span>
        </div>
    );
}
