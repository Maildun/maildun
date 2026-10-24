import {
    ArrowDown01Icon,
    ArrowUp01Icon,
    CheckmarkCircle02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export type AssistantStep = { name: string; detail: string | null };

export type AssistantOutcome = 'working' | 'proposed' | 'answered' | 'failed';

/** Each tool as [in progress, finished], read like a short log. */
const STEP_LABELS: Record<string, [string, string]> = {
    get_document: ['Reading the email', 'Read the email'],
    get_block: ['Reading block', 'Read block'],
    insert_blocks: ['Adding', 'Added'],
    update_block: ['Editing', 'Edited'],
    move_block: ['Moving', 'Moved'],
    remove_block: ['Removing', 'Removed'],
    duplicate_block: ['Duplicating', 'Duplicated'],
    replace_block: ['Replacing', 'Replaced'],
    insert_section: ['Adding section', 'Added section'],
    list_saved_sections: [
        'Checking your saved sections',
        'Checked your saved sections',
    ],
    insert_saved_section: ['Adding your section', 'Added your section'],
    update_settings: ['Updating email settings', 'Updated email settings'],
    update_theme: ['Updating colors and fonts', 'Updated colors and fonts'],
    replace_document: ['Rebuilding the email', 'Rebuilt the email'],
    apply_ops: ['Applying', 'Applied'],
    check_email: ['Checking the email', 'Checked the email'],
    list_media: ['Searching your media', 'Searched your media'],
};

const HEADINGS: Record<AssistantOutcome, string> = {
    working: 'Working on the design',
    proposed: 'Updated the design',
    answered: 'Reviewed the design',
    failed: 'Stopped',
};

/** Text with a light sweeping across it; plain muted text when motion is reduced. */
const SHIMMER =
    'animate-shimmer bg-[linear-gradient(90deg,color-mix(in_oklab,var(--color-muted-foreground),transparent_45%)_30%,var(--color-foreground)_45%,var(--color-foreground)_55%,color-mix(in_oklab,var(--color-muted-foreground),transparent_45%)_70%)] bg-size-[250%_100%] bg-clip-text text-transparent motion-reduce:animate-none motion-reduce:bg-none motion-reduce:text-muted-foreground';

/** Per-row delay when the log opens (top down) or closes (bottom up), capped for long logs. */
const STAGGER_MS = 30;
const MAX_STAGGER_STEPS = 10;

type StepRow = AssistantStep & {
    key: number;
    count: number;
    current: boolean;
};

/**
 * Finished steps that repeat (the same tool on the same block, or another
 * check) merge into their first row with a count; the running step stays last.
 */
function stepRows(steps: AssistantStep[], working: boolean): StepRow[] {
    const finished = working ? steps.slice(0, -1) : steps;
    const rows = new Map<string, StepRow>();

    finished.forEach((step, index) => {
        const id = `${step.name}\u0000${step.detail ?? ''}`;
        const row = rows.get(id);

        if (row) {
            row.count += 1;
        } else {
            rows.set(id, { ...step, key: index, count: 1, current: false });
        }
    });

    const list = [...rows.values()];

    if (working && steps.length > 0) {
        list.push({
            ...steps[steps.length - 1],
            key: steps.length - 1,
            count: 1,
            current: true,
        });
    }

    return list;
}

/**
 * The assistant's tool calls as a collapsible log: finished steps are checked,
 * the current one spins and shimmers, and new steps slide in. The log is open
 * while the turn runs and folds away when it ends, leaving the reply in view;
 * rows fade in one after another when it opens and out in reverse when it closes.
 */
export function DesignAssistantActivity({
    steps,
    outcome,
}: {
    steps: AssistantStep[];
    outcome: AssistantOutcome;
}) {
    const working = outcome === 'working';
    const [expanded, setExpanded] = useState(working);
    const [wasWorking, setWasWorking] = useState(working);

    // Open while the turn runs, folded once it ends (the author can still toggle it).
    if (working !== wasWorking) {
        setWasWorking(working);
        setExpanded(working);
    }

    if (working && steps.length === 0) {
        return (
            <p
                className="flex items-center gap-2 text-sm font-medium"
                role="status"
            >
                <AppLogoIcon animate="think" className="size-4" />
                <span className={SHIMMER}>Thinking</span>
            </p>
        );
    }

    if (steps.length === 0) {
        return null;
    }

    const rows = stepRows(steps, working);

    return (
        <div className="flex flex-col" data-test="assistant-activity">
            <button
                type="button"
                className="group flex items-center gap-2 text-left text-sm font-medium text-muted-foreground"
                aria-expanded={expanded}
                onClick={() => setExpanded((open) => !open)}
            >
                <AppLogoIcon
                    animate={working ? 'think' : 'none'}
                    className="size-4"
                />
                <span
                    className={cn('flex-1', working && SHIMMER)}
                    role="status"
                >
                    {HEADINGS[outcome]}
                </span>
                <HugeiconsIcon
                    icon={expanded ? ArrowUp01Icon : ArrowDown01Icon}
                    className="size-4 shrink-0 opacity-60 group-hover:opacity-100"
                />
            </button>
            <div
                inert={!expanded}
                className={cn(
                    'grid transition-[grid-template-rows] duration-300 ease-out motion-reduce:transition-none',
                    expanded ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                )}
            >
                <ol className="min-h-0 overflow-hidden">
                    {rows.map(({ key, count, current, ...step }, index) => {
                        const [running, done] = STEP_LABELS[step.name] ?? [
                            'Working',
                            'Worked',
                        ];
                        const order = expanded
                            ? index
                            : rows.length - 1 - index;

                        return (
                            <li
                                key={key}
                                style={{
                                    transitionDelay: `${Math.min(order, MAX_STAGGER_STEPS) * STAGGER_MS}ms`,
                                }}
                                className={cn(
                                    'flex min-h-6 animate-in items-center gap-2 pl-0.5 text-xs text-muted-foreground transition-[opacity,translate] duration-200 ease-out fade-in slide-in-from-top-1 first:mt-1 motion-reduce:animate-none motion-reduce:transition-none',
                                    expanded
                                        ? 'translate-y-0 opacity-100'
                                        : '-translate-y-1 opacity-0',
                                )}
                            >
                                {current ? (
                                    <Spinner
                                        className="size-3.5 shrink-0"
                                        aria-hidden
                                    />
                                ) : (
                                    <HugeiconsIcon
                                        icon={CheckmarkCircle02Icon}
                                        className="size-3.5 shrink-0 text-muted-foreground/60"
                                        aria-hidden
                                    />
                                )}
                                <span className={cn(current && SHIMMER)}>
                                    {current ? running : done}
                                </span>
                                {step.detail && (
                                    <span className="truncate rounded border bg-muted px-1 py-px font-mono text-[11px] text-foreground">
                                        {step.detail}
                                    </span>
                                )}
                                {count > 1 && (
                                    <span className="text-[11px] text-muted-foreground/70 tabular-nums">
                                        ×{count}
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </div>
        </div>
    );
}
