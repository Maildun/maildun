import {
    Add01Icon,
    ArrowRight01Icon,
    MailSend02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import type { EmailProviderIntegration } from '@/components/team-email-provider-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { formatRelativeTime } from '@/lib/format';
import { create, show } from '@/routes/teams/email-provider';

type TeamSummary = {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    logo: string | null;
    isPersonal: boolean;
};

type ProviderOption = {
    value: string;
    label: string;
    description: string;
};

type Props = {
    team: TeamSummary;
    providers: ProviderOption[];
    integration: EmailProviderIntegration | null;
    sender: {
        name: string | null;
        address: string | null;
        is_verified: boolean;
    };
    canManage: boolean;
};

function relativeTimestamp(value: string): string {
    const relative = formatRelativeTime(value);

    if (relative === 'Now') {
        return 'Just now';
    }

    return /^\d+[mhd]$/.test(relative) ? `${relative} ago` : relative;
}

export default function TeamEmailProviderPage({
    team,
    providers,
    integration,
    sender,
    canManage,
}: Props) {
    const [addProviderOpen, setAddProviderOpen] = useState(false);

    return (
        <>
            <Head title={`Email delivery · ${team.name}`} />

            <div
                className="flex flex-col gap-8"
                data-test="email-provider-page"
            >
                <Dialog
                    open={addProviderOpen}
                    onOpenChange={setAddProviderOpen}
                >
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <SettingsPageHeader
                            title="Email delivery"
                            description="Connect and test the one delivery provider used by this workspace. Sender addresses are verified separately after delivery works."
                        />
                        {canManage && integration === null ? (
                            <DialogTrigger render={<Button type="button" />}>
                                <HugeiconsIcon
                                    icon={Add01Icon}
                                    data-icon="inline-start"
                                />
                                Connect provider
                            </DialogTrigger>
                        ) : null}
                    </div>

                    <DialogContent className="w-2xl">
                        <DialogHeader>
                            <DialogTitle>Connect email provider</DialogTitle>
                            <DialogDescription>
                                A workspace uses one delivery connection. Test
                                it with an explicit From and To address before
                                adding workspace senders.
                            </DialogDescription>
                        </DialogHeader>
                        <div
                            className="grid max-h-[60vh] gap-3 overflow-y-auto sm:grid-cols-2"
                            data-test="email-provider-options"
                        >
                            {providers.map((option) => (
                                <Link
                                    key={option.value}
                                    href={create({
                                        team: team.slug,
                                        provider: option.value,
                                    })}
                                    prefetch
                                    className="group flex min-w-0 items-start gap-3 rounded-lg border bg-background p-4 transition-colors hover:bg-muted/50 focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                    data-test={`email-provider-option-${option.value}`}
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-card shadow-xs">
                                        <HugeiconsIcon
                                            icon={MailSend02Icon}
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                                        <span className="flex flex-wrap items-center gap-2 font-medium">
                                            {option.label}
                                            {option.value === 'ses' ? (
                                                <Badge variant="secondary">
                                                    Recommended
                                                </Badge>
                                            ) : null}
                                        </span>
                                        <span className="text-xs leading-relaxed text-muted-foreground">
                                            {option.description}
                                        </span>
                                    </span>
                                    <HugeiconsIcon
                                        icon={ArrowRight01Icon}
                                        className="mt-1 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            ))}
                        </div>
                    </DialogContent>
                </Dialog>

                <SettingsPanel
                    variant="inset"
                    title="Workspace delivery connection"
                    description="Campaign and transactional email use this connection only after delivery and the exact From address are verified."
                >
                    {integration ? (
                        <div
                            className="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-7"
                            data-test="connected-email-provider"
                        >
                            <div className="flex min-w-0 items-start gap-4">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-xl border bg-background shadow-xs">
                                    <HugeiconsIcon
                                        icon={MailSend02Icon}
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="flex min-w-0 flex-col gap-1.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-heading text-lg font-semibold tracking-tight">
                                            {integration.name}
                                        </h2>
                                        <Badge
                                            variant={
                                                integration.delivery_is_verified
                                                    ? 'success'
                                                    : 'orange'
                                            }
                                            data-test="email-provider-status"
                                        >
                                            {integration.delivery_is_verified
                                                ? 'Delivery verified'
                                                : 'Delivery test required'}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {integration.provider_label}
                                        {integration.last_tested_at
                                            ? ` · Tested ${relativeTimestamp(integration.last_tested_at)}`
                                            : ' · Not tested yet'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {integration.verified_sender_count}{' '}
                                        verified{' '}
                                        {integration.verified_sender_count === 1
                                            ? 'sender'
                                            : 'senders'}{' '}
                                        for the current connection.
                                    </p>
                                    {sender.address ? (
                                        <p className="text-xs text-muted-foreground">
                                            Default: {sender.address} ·{' '}
                                            {sender.is_verified
                                                ? 'verified'
                                                : 'not verified for this connection'}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                className="w-full sm:w-auto"
                                nativeButton={false}
                                render={
                                    <Link
                                        href={show({
                                            team: team.slug,
                                            emailIntegration: integration.uuid,
                                        })}
                                        prefetch
                                    />
                                }
                                data-test={`manage-email-provider-${integration.uuid}`}
                            >
                                Manage connection
                                <HugeiconsIcon
                                    icon={ArrowRight01Icon}
                                    data-icon="inline-end"
                                />
                            </Button>
                        </div>
                    ) : (
                        <Empty className="border-0 py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon icon={MailSend02Icon} />
                                </EmptyMedia>
                                <EmptyTitle>No delivery connection</EmptyTitle>
                                <EmptyDescription>
                                    Connect SMTP or Amazon SES, then send a
                                    delivery test before registering sender
                                    addresses.
                                </EmptyDescription>
                            </EmptyHeader>
                            {canManage ? (
                                <EmptyContent>
                                    <Button
                                        type="button"
                                        onClick={() => setAddProviderOpen(true)}
                                    >
                                        <HugeiconsIcon
                                            icon={Add01Icon}
                                            data-icon="inline-start"
                                        />
                                        Connect provider
                                    </Button>
                                </EmptyContent>
                            ) : null}
                        </Empty>
                    )}
                </SettingsPanel>

                {!canManage ? (
                    <p className="text-sm text-muted-foreground">
                        Only workspace owners and admins can manage email
                        delivery.
                    </p>
                ) : null}
            </div>
        </>
    );
}
