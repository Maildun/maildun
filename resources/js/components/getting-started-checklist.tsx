import {
    ArrowDown01Icon,
    MailAtSign02Icon,
    MailSend02Icon,
    NodeEditIcon,
    UserAdd01Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link, usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { CheckmarkCircleSolidIcon } from '@/components/icons/toast-status-icons';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { cn } from '@/lib/utils';
import { index as audiences } from '@/routes/audiences';
import { index as automations } from '@/routes/automations';
import { index as contacts } from '@/routes/contacts';
import { index as emails } from '@/routes/emails';
import { edit as teamEmailProviderSettings } from '@/routes/teams/email-provider';
import { edit as teamSenderSettings } from '@/routes/teams/sender';
import type { OnboardingStepKey } from '@/types';

type StepCopy = {
    label: string;
    title: string;
    description: string;
    action: string;
    icon: typeof MailAtSign02Icon;
    href: (teamSlug: string) => string;
};

const STEP_COPY: Record<OnboardingStepKey, StepCopy> = {
    delivery: {
        label: 'Connect email delivery',
        title: 'Connect your email provider',
        description:
            'Add your SMTP or Amazon SES credentials and send a successful test before Maildun can deliver email.',
        action: 'Set up email delivery',
        icon: MailSend02Icon,
        href: (teamSlug) => teamEmailProviderSettings.url(teamSlug),
    },
    sender: {
        label: 'Set your sender',
        title: 'Set your sending address',
        description:
            'Register and verify the From address and reply-to that your contacts will see on every email.',
        action: 'Open sender settings',
        icon: MailAtSign02Icon,
        href: (teamSlug) => teamSenderSettings.url(teamSlug),
    },
    audience: {
        label: 'Create an audience',
        title: 'Create your first audience',
        description:
            'An audience holds your subscribers, the attributes you collect, and the segments you send to.',
        action: 'Go to audiences',
        icon: UserGroupIcon,
        href: (teamSlug) => audiences.url(teamSlug),
    },
    subscribers: {
        label: 'Add contacts',
        title: 'Add your first contact',
        description:
            'Add or import contacts, then subscribe them to an audience so they can receive campaigns.',
        action: 'Go to contacts',
        icon: UserAdd01Icon,
        href: (teamSlug) => contacts.url(teamSlug),
    },
    campaign: {
        label: 'Send a campaign',
        title: 'Send your first campaign',
        description:
            'Design an email in the builder, pick the audience it goes to, and send it when it looks right.',
        action: 'Go to campaigns',
        icon: MailAtSign02Icon,
        href: (teamSlug) => emails.url(teamSlug),
    },
    automation: {
        label: 'Build an automation',
        title: 'Automate a welcome email',
        description:
            'Automations send on their own — when someone subscribes, joins a segment, or after a wait step.',
        action: 'Go to automations',
        icon: NodeEditIcon,
        href: (teamSlug) => automations.url(teamSlug),
    },
};

const STORAGE_KEY = 'getting-started-collapsed';
const listeners = new Set<() => void>();

let collapsed =
    typeof window !== 'undefined' &&
    window.localStorage.getItem(STORAGE_KEY) === 'true';

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

function setCollapsed(value: boolean): void {
    collapsed = value;
    window.localStorage.setItem(STORAGE_KEY, String(value));
    listeners.forEach((listener) => listener());
}

function useCollapsed(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => collapsed,
        () => false,
    );
}

export function GettingStartedChecklist() {
    const { currentTeam, onboarding } = usePage().props;
    const isCollapsed = useCollapsed();

    if (
        !currentTeam ||
        !onboarding ||
        onboarding.completed >= onboarding.total
    ) {
        return null;
    }

    return (
        <div className="p-2 group-data-[collapsible=icon]:hidden">
            <OrderedDitherFilter />
            <Collapsible
                open={!isCollapsed}
                onOpenChange={(open) => setCollapsed(!open)}
                className="rounded-xl bg-card text-card-foreground shadow-xs ring-1 ring-foreground/10"
            >
                <CollapsibleTrigger
                    render={
                        <Button
                            variant="ghost"
                            className="h-auto w-full items-start justify-start gap-2 rounded-xl p-3 text-left whitespace-normal"
                        />
                    }
                    aria-label={
                        isCollapsed
                            ? 'Show getting started steps'
                            : 'Hide getting started steps'
                    }
                >
                    <ProgressRing
                        completed={onboarding.completed}
                        total={onboarding.total}
                    />
                    <div className="min-w-0 flex-1">
                        <p className="font-heading text-sm leading-snug font-medium">
                            Getting started
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {onboarding.completed}/{onboarding.total} steps
                            completed
                        </p>
                    </div>
                    <span className="flex size-6 shrink-0 items-center justify-center">
                        <HugeiconsIcon
                            icon={ArrowDown01Icon}
                            className={cn(
                                'text-muted-foreground transition-transform duration-200',
                                isCollapsed && '-rotate-90',
                            )}
                        />
                    </span>
                </CollapsibleTrigger>

                <CollapsibleContent className="h-(--collapsible-panel-height) overflow-hidden transition-[height,opacity] duration-300 ease-out data-ending-style:h-0 data-ending-style:opacity-0 data-starting-style:h-0 data-starting-style:opacity-0 motion-reduce:transition-none">
                    <ul className="flex flex-col gap-0.5 px-2 pb-2">
                        {onboarding.steps.map((step, index) => (
                            <ChecklistStep
                                key={step.key}
                                stepKey={step.key}
                                completed={step.completed}
                                teamSlug={currentTeam.slug}
                                index={index}
                            />
                        ))}
                    </ul>
                </CollapsibleContent>
            </Collapsible>
        </div>
    );
}

const DITHER_TILE_SIZE = 8;
const DITHER_LEVELS = 10;

/**
 * Recursive-doubling Bayer matrix: the classic ordered-dither threshold map.
 * Each cell holds its position in the 0..(size^2 - 1) threshold order.
 */
function buildBayerMatrix(size: number): number[][] {
    let matrix = [[0]];

    while (matrix.length < size) {
        const half = matrix.length;
        const next = Array.from({ length: half * 2 }, () =>
            new Array<number>(half * 2).fill(0),
        );

        for (let y = 0; y < half; y++) {
            for (let x = 0; x < half; x++) {
                const base = matrix[y][x] * 4;
                next[y][x] = base;
                next[y][x + half] = base + 2;
                next[y + half][x] = base + 3;
                next[y + half][x + half] = base + 1;
            }
        }

        matrix = next;
    }

    return matrix;
}

/** The Bayer matrix as a one-pixel-per-cell greyscale SVG tile. */
const DITHER_TILE = (() => {
    const matrix = buildBayerMatrix(DITHER_TILE_SIZE);
    const cells = DITHER_TILE_SIZE * DITHER_TILE_SIZE;
    const rects = matrix
        .flatMap((row, y) =>
            row.map((threshold, x) => {
                const grey = Math.round((255 * threshold) / (cells - 1));

                return `<rect x="${x}" y="${y}" width="1" height="1" fill="rgb(${grey},${grey},${grey})"/>`;
            }),
        )
        .join('');

    return `data:image/svg+xml,${encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="${DITHER_TILE_SIZE}" height="${DITHER_TILE_SIZE}" shape-rendering="crispEdges">${rects}</svg>`,
    )}`;
})();

const DITHER_TABLE = Array.from({ length: DITHER_LEVELS }, (_, step) =>
    (step / (DITHER_LEVELS - 1)).toFixed(4),
).join(' ');

/**
 * Consumed by the `dithered` utility in app.css. Tiles the Bayer map across the
 * surface, adds it to the source, then quantises each channel — ordered
 * dithering, so the wash bands into pixel dots. Kept at `size-0` rather than
 * `hidden` because Firefox will not resolve a filter inside `display:none`.
 */
function OrderedDitherFilter() {
    return (
        <svg aria-hidden="true" focusable="false" className="absolute size-0">
            <filter
                id="ordered-dither"
                x="0"
                y="0"
                width="100%"
                height="100%"
                colorInterpolationFilters="sRGB"
            >
                <feImage
                    href={DITHER_TILE}
                    x="0"
                    y="0"
                    width={DITHER_TILE_SIZE}
                    height={DITHER_TILE_SIZE}
                    result="tile"
                />
                <feTile in="tile" result="threshold" />
                <feComposite
                    in="SourceGraphic"
                    in2="threshold"
                    operator="arithmetic"
                    k1={0}
                    k2={1}
                    k3={0.24}
                    k4={-0.12}
                    result="thresholded"
                />
                <feComponentTransfer in="thresholded">
                    <feFuncR type="discrete" tableValues={DITHER_TABLE} />
                    <feFuncG type="discrete" tableValues={DITHER_TABLE} />
                    <feFuncB type="discrete" tableValues={DITHER_TABLE} />
                </feComponentTransfer>
            </filter>
        </svg>
    );
}

function ChecklistStep({
    stepKey,
    completed,
    teamSlug,
    index,
}: {
    stepKey: OnboardingStepKey;
    completed: boolean;
    teamSlug: string;
    index: number;
}) {
    const copy = STEP_COPY[stepKey];
    const href = copy.href(teamSlug);

    return (
        <li
            className="motion-safe:animate-in motion-safe:duration-300 motion-safe:fade-in-0 motion-safe:fill-mode-backwards motion-safe:slide-in-from-bottom-1"
            style={{ animationDelay: `${index * 45}ms` }}
        >
            <HoverCard>
                <HoverCardTrigger
                    render={
                        <Link
                            href={href}
                            prefetch
                            className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm ring-ring outline-hidden hover:bg-muted focus-visible:ring-2"
                        />
                    }
                >
                    {completed ? (
                        <CheckmarkCircleSolidIcon className="size-4 shrink-0 text-info" />
                    ) : (
                        <span className="size-4 shrink-0 rounded-full border-2 border-border" />
                    )}
                    <span
                        className={cn(
                            'truncate',
                            completed && 'text-muted-foreground',
                        )}
                    >
                        {copy.label}
                    </span>
                </HoverCardTrigger>
                <HoverCardContent
                    side="right"
                    align="start"
                    sideOffset={12}
                    className="w-72 overflow-hidden rounded-xl p-2"
                >
                    <div className="dithered relative flex aspect-video soft-wash items-center justify-center overflow-hidden rounded-lg">
                        <HugeiconsIcon
                            icon={copy.icon}
                            strokeWidth={1.5}
                            className="size-14 text-white drop-shadow-sm"
                        />
                    </div>
                    <div className="flex flex-col gap-1 p-2">
                        <p className="font-heading text-sm font-medium">
                            {copy.title}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {copy.description}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-2 self-start"
                            nativeButton={false}
                            render={<Link href={href} prefetch />}
                        >
                            {completed ? 'Review' : copy.action}
                        </Button>
                    </div>
                </HoverCardContent>
            </HoverCard>
        </li>
    );
}

function ProgressRing({
    completed,
    total,
}: {
    completed: number;
    total: number;
}) {
    const radius = 8;
    const circumference = 2 * Math.PI * radius;
    const progress = total === 0 ? 0 : completed / total;

    return (
        <svg
            viewBox="0 0 20 20"
            className="mt-0.5 size-5 shrink-0 -rotate-90"
            aria-hidden="true"
        >
            <circle
                cx="10"
                cy="10"
                r={radius}
                fill="none"
                strokeWidth="3"
                className="stroke-muted-foreground/20"
            />
            <circle
                cx="10"
                cy="10"
                r={radius}
                fill="none"
                strokeWidth="3"
                strokeLinecap="round"
                strokeDasharray={circumference}
                strokeDashoffset={circumference * (1 - progress)}
                className="stroke-primary transition-[stroke-dashoffset] duration-500"
            />
        </svg>
    );
}
