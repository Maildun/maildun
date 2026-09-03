import { ClockFadingIcon, MailRemove01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroyInactive,
    destroyUnconfirmed,
} from '@/actions/App/Http/Controllers/AudienceHygieneController';
import Heading from '@/components/heading';
import { SettingsPanel } from '@/components/settings-panel';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AudienceSettingsLayout from '@/layouts/audiences/settings-layout';
import type { Audience } from '@/types/audiences';

type HygieneKind = 'unconfirmed' | 'inactive';

type Props = {
    audience: Pick<Audience, 'uuid' | 'name' | 'avatar'>;
    counts: Record<HygieneKind, number>;
};

const hygieneCopy: Record<
    HygieneKind,
    {
        title: string;
        description: string;
        criteria: string;
        action: string;
        confirmTitle: string;
        confirmDescription: (count: number) => string;
        icon: typeof MailRemove01Icon;
    }
> = {
    unconfirmed: {
        title: 'Unconfirmed subscribers',
        description:
            'Remove people who joined through double opt-in but never confirmed their subscription.',
        criteria: 'Subscribed records without a confirmation timestamp.',
        action: 'Remove unconfirmed',
        confirmTitle: 'Remove unconfirmed subscribers?',
        confirmDescription: (count) =>
            `This permanently removes ${count.toLocaleString()} unconfirmed ${count === 1 ? 'subscriber' : 'subscribers'} and their consent records. Past campaign delivery records are retained for reporting.`,
        icon: MailRemove01Icon,
    },
    inactive: {
        title: 'Inactive subscribers',
        description:
            'Remove people who have never opened or clicked a campaign sent to them.',
        criteria:
            'Currently subscribed, received at least one sent campaign, and has no opens or clicks.',
        action: 'Remove inactive',
        confirmTitle: 'Remove inactive subscribers?',
        confirmDescription: (count) =>
            `This permanently removes ${count.toLocaleString()} inactive ${count === 1 ? 'subscriber' : 'subscribers'} and their consent records. Past campaign delivery records are retained for reporting.`,
        icon: ClockFadingIcon,
    },
};

export default function AudienceHygieneSettings({ audience, counts }: Props) {
    const { currentTeam } = usePage().props;
    const [confirmation, setConfirmation] = useState<HygieneKind | null>(null);
    const [processing, setProcessing] = useState(false);

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];

    const removeSubscribers = (kind: HygieneKind) => {
        const action =
            kind === 'unconfirmed'
                ? destroyUnconfirmed(routeArgs)
                : destroyInactive(routeArgs);

        router.delete(action.url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => setConfirmation(null),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`List hygiene · ${audience.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="List hygiene"
                    description="Keep this audience focused by permanently removing subscribers who are unconfirmed or have never engaged."
                />

                <div className="flex flex-col gap-6">
                    {(Object.keys(hygieneCopy) as HygieneKind[]).map((kind) => {
                        const copy = hygieneCopy[kind];
                        const count = counts[kind];

                        return (
                            <SettingsPanel
                                key={kind}
                                variant="inset"
                                title={copy.title}
                                description={copy.description}
                                actions={
                                    <Badge variant="secondary">
                                        {count.toLocaleString()}
                                    </Badge>
                                }
                            >
                                <div className="flex w-full flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-8 sm:px-7">
                                    <div className="flex items-start gap-3">
                                        <HugeiconsIcon
                                            icon={copy.icon}
                                            className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            {copy.criteria}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        className="shrink-0"
                                        disabled={count === 0}
                                        onClick={() => setConfirmation(kind)}
                                    >
                                        {copy.action}
                                    </Button>
                                </div>
                            </SettingsPanel>
                        );
                    })}
                </div>

                <AlertDialog
                    open={confirmation !== null}
                    onOpenChange={(open) => {
                        if (!open && !processing) {
                            setConfirmation(null);
                        }
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                {confirmation
                                    ? hygieneCopy[confirmation].confirmTitle
                                    : 'Remove subscribers?'}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {confirmation
                                    ? hygieneCopy[
                                          confirmation
                                      ].confirmDescription(counts[confirmation])
                                    : 'This action cannot be undone.'}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={processing}>
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction
                                variant="destructive"
                                disabled={processing || confirmation === null}
                                onClick={() => {
                                    if (confirmation) {
                                        removeSubscribers(confirmation);
                                    }
                                }}
                            >
                                {processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Remove permanently
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AudienceSettingsLayout>
    );
}
