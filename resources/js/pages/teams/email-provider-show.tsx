import {
    Alert01Icon,
    ArrowLeft01Icon,
    CheckmarkCircle02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Deferred, Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import {
    SECRET_FIELD_NAMES,
    TeamEmailProviderFields,
} from '@/components/team-email-provider-fields';
import type { EmailProviderIntegration } from '@/components/team-email-provider-fields';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import { formatRelativeTime } from '@/lib/format';
import {
    destroy,
    edit,
    store,
    test as testConnection,
    update,
} from '@/routes/teams/email-provider';
import { edit as editSenders } from '@/routes/teams/sender';
import type { SesAccountLimits } from '@/types';

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
    provider: ProviderOption;
    integration: EmailProviderIntegration | null;
    sender: {
        name: string | null;
        address: string | null;
        is_verified: boolean;
    };
    canManage: boolean;
    webhookUrl: string;
    /** Deferred: undefined until loaded, null when the connection is not tested. */
    sesLimits?: SesAccountLimits | null;
};

function relativeTimestamp(value: string): string {
    const relative = formatRelativeTime(value);

    if (relative === 'Now') {
        return 'Just now';
    }

    return /^\d+[mhd]$/.test(relative) ? `${relative} ago` : relative;
}

export default function TeamEmailProviderShowPage({
    sesLimits,
    team,
    provider,
    integration,
    sender,
    canManage,
    webhookUrl,
}: Props) {
    const [testOpen, setTestOpen] = useState(false);
    const [disconnectOpen, setDisconnectOpen] = useState(false);
    const [trustProviderSenders, setTrustProviderSenders] = useState(
        integration?.trust_provider_senders ?? false,
    );
    const connectionName = integration?.name ?? `${provider.label} connection`;
    const saveForm = integration
        ? update.form.patch({
              team: team.slug,
              emailIntegration: integration.uuid,
          })
        : store.form.post(team.slug);

    return (
        <>
            <Head title={`${connectionName} · Email delivery · ${team.name}`} />

            <div
                className="flex flex-col gap-8"
                data-test="email-provider-detail-page"
            >
                <Link
                    href={edit(team.slug)}
                    prefetch
                    className="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} aria-hidden="true" />
                    Email delivery
                </Link>

                <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <SettingsPageHeader
                        title={connectionName}
                        description={provider.description}
                    />
                    {canManage && integration ? (
                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row lg:shrink-0">
                            <Button
                                type="button"
                                className="w-full sm:w-auto"
                                onClick={() => setTestOpen(true)}
                                data-test="test-email-provider"
                            >
                                Test delivery
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => setDisconnectOpen(true)}
                            >
                                Disconnect
                            </Button>
                        </div>
                    ) : null}
                </div>

                {integration?.feedback?.stale ? (
                    <Alert variant="warning" data-test="ses-feedback-stale">
                        <AlertTitle>
                            Amazon SES has stopped reporting back
                        </AlertTitle>
                        <AlertDescription>
                            Mail went out through this connection more than an
                            hour ago, but no delivery, bounce or complaint
                            feedback has arrived since. Check that the SNS topic
                            is still subscribed to the webhook URL below; until
                            it is, campaigns show no delivery results and hard
                            bounces are not suppressed.
                        </AlertDescription>
                    </Alert>
                ) : null}
                {integration ? (
                    <Alert data-test="delivery-verification-status">
                        <HugeiconsIcon
                            icon={
                                integration.delivery_is_verified
                                    ? CheckmarkCircle02Icon
                                    : Alert01Icon
                            }
                            aria-hidden="true"
                        />
                        <AlertTitle>
                            {integration.delivery_is_verified
                                ? 'Delivery verified'
                                : 'Delivery test required'}
                        </AlertTitle>
                        <AlertDescription>
                            {integration.delivery_is_verified
                                ? `The provider accepted a test from ${integration.test_from_address}. You can now verify workspace sender addresses.`
                                : 'Send a successful test with an explicit From and To address. This tests the provider credentials without depending on a workspace sender.'}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <SettingsPanel variant="inset" title="Delivery overview">
                    <dl className="flex flex-col">
                        <div className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr] sm:items-center">
                            <dt className="text-sm text-muted-foreground">
                                Provider
                            </dt>
                            <dd className="text-sm font-medium">
                                {provider.label}
                            </dd>
                        </div>
                        <Separator />
                        <div className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr] sm:items-center">
                            <dt className="text-sm text-muted-foreground">
                                Status
                            </dt>
                            <dd className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                <Badge
                                    variant={
                                        integration?.delivery_is_verified
                                            ? 'success'
                                            : integration
                                              ? 'orange'
                                              : 'outline'
                                    }
                                >
                                    {integration?.delivery_is_verified
                                        ? 'Verified'
                                        : integration
                                          ? 'Test required'
                                          : 'Not connected'}
                                </Badge>
                                {integration?.connected_at ? (
                                    <span className="text-muted-foreground">
                                        Connected{' '}
                                        {relativeTimestamp(
                                            integration.connected_at,
                                        )}
                                    </span>
                                ) : null}
                            </dd>
                        </div>
                        <Separator />
                        <div className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr] sm:items-center">
                            <dt className="text-sm text-muted-foreground">
                                Last delivery test
                            </dt>
                            <dd className="text-sm font-medium">
                                {integration?.last_tested_at
                                    ? `${relativeTimestamp(integration.last_tested_at)} from ${integration.test_from_address}`
                                    : 'Not tested yet'}
                            </dd>
                        </div>
                        <Separator />
                        <div className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr] sm:items-center">
                            <dt className="text-sm text-muted-foreground">
                                Verified senders
                            </dt>
                            <dd className="flex flex-wrap items-center gap-3 text-sm font-medium">
                                {integration?.verified_sender_count ?? 0}
                                {integration?.delivery_is_verified ? (
                                    <Link
                                        href={editSenders(team.slug)}
                                        className="text-primary hover:underline"
                                    >
                                        Manage senders
                                    </Link>
                                ) : null}
                            </dd>
                        </div>
                        {integration?.feedback ? (
                            <>
                                <Separator />
                                <div className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr] sm:items-center">
                                    <dt className="text-sm text-muted-foreground">
                                        Last SES feedback
                                    </dt>
                                    <dd
                                        className="text-sm font-medium"
                                        data-test="ses-feedback-heartbeat"
                                    >
                                        {integration.feedback.last_feedback_at
                                            ? relativeTimestamp(
                                                  integration.feedback
                                                      .last_feedback_at,
                                              )
                                            : 'None received yet'}
                                    </dd>
                                </div>
                            </>
                        ) : null}
                        {integration?.provider === 'ses' ? (
                            <>
                                <Separator />
                                <div className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr] sm:items-center">
                                    <dt className="text-sm text-muted-foreground">
                                        Sending quota
                                    </dt>
                                    <dd
                                        className="text-sm font-medium"
                                        data-test="ses-sending-quota"
                                    >
                                        <Deferred
                                            data="sesLimits"
                                            fallback={
                                                <Skeleton className="h-4 w-56 animate-pulse" />
                                            }
                                        >
                                            <SesLimitsSummary
                                                limits={sesLimits ?? null}
                                            />
                                        </Deferred>
                                    </dd>
                                </div>
                            </>
                        ) : null}
                        {sender.address ? (
                            <>
                                <Separator />
                                <div className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr] sm:items-center">
                                    <dt className="text-sm text-muted-foreground">
                                        Default sender
                                    </dt>
                                    <dd className="text-sm font-medium">
                                        {sender.address} ·{' '}
                                        {sender.is_verified
                                            ? 'verified'
                                            : 'retest required'}
                                    </dd>
                                </div>
                            </>
                        ) : null}
                    </dl>
                </SettingsPanel>

                {canManage ? (
                    <Form
                        key={`${team.slug}:${provider.value}:${integration?.uuid ?? 'new'}`}
                        {...saveForm}
                        options={{ preserveScroll: true }}
                        resetOnSuccess={SECRET_FIELD_NAMES}
                        setDefaultsOnSuccess
                        onError={() => focusFirstInvalidField()}
                        data-test="email-provider-form"
                    >
                        {({ errors, processing, isDirty }) => {
                            const hasProviderTrustChanges =
                                trustProviderSenders !==
                                (integration?.trust_provider_senders ?? false);
                            const formIsDirty =
                                isDirty || hasProviderTrustChanges;

                            return (
                                <>
                                    <UnsavedChangesGuard
                                        isDirty={formIsDirty}
                                    />
                                    <SettingsPanel
                                        variant="inset"
                                        title={
                                            integration
                                                ? 'Connection settings'
                                                : `Connect ${provider.label}`
                                        }
                                        description="Changing provider credentials invalidates the delivery test and every sender proof. Changing only the connection name does not."
                                    >
                                        <div className="flex flex-col gap-6 p-6 sm:p-7">
                                            <input
                                                type="hidden"
                                                name="provider"
                                                value={provider.value}
                                            />
                                            <input
                                                type="hidden"
                                                name="trust_provider_senders"
                                                value={
                                                    trustProviderSenders
                                                        ? '1'
                                                        : '0'
                                                }
                                            />
                                            <FieldGroup>
                                                <Field
                                                    data-invalid={Boolean(
                                                        errors.name,
                                                    )}
                                                >
                                                    <FieldLabel htmlFor="email_provider_name">
                                                        Connection name
                                                    </FieldLabel>
                                                    <Input
                                                        id="email_provider_name"
                                                        name="name"
                                                        defaultValue={
                                                            connectionName
                                                        }
                                                        placeholder="Production SES"
                                                        required
                                                        maxLength={100}
                                                        aria-invalid={Boolean(
                                                            errors.name,
                                                        )}
                                                        data-test="email-provider-name"
                                                    />
                                                    <FieldDescription>
                                                        A label for this
                                                        provider account or
                                                        environment.
                                                    </FieldDescription>
                                                    <FieldError>
                                                        {errors.name}
                                                    </FieldError>
                                                </Field>
                                            </FieldGroup>
                                            <TeamEmailProviderFields
                                                provider={provider.value}
                                                integration={integration}
                                                errors={errors}
                                                webhookUrl={webhookUrl}
                                            />
                                            <Field
                                                orientation="horizontal"
                                                className="rounded-lg border p-4"
                                            >
                                                <Checkbox
                                                    id="trust_provider_senders"
                                                    checked={
                                                        trustProviderSenders
                                                    }
                                                    onCheckedChange={
                                                        setTrustProviderSenders
                                                    }
                                                    data-test="trust-provider-senders"
                                                />
                                                <FieldContent>
                                                    <FieldLabel htmlFor="trust_provider_senders">
                                                        Trust this provider for
                                                        senders
                                                    </FieldLabel>
                                                    <FieldDescription>
                                                        After a successful
                                                        delivery test, Maildun
                                                        authorizes registered
                                                        sender addresses on the
                                                        same domain without a
                                                        Maildun DNS record or
                                                        verification email.
                                                        Enable this only when
                                                        your provider already
                                                        verifies that domain.
                                                    </FieldDescription>
                                                </FieldContent>
                                            </Field>
                                        </div>
                                        <div className="flex items-center justify-end border-t px-6 py-5 sm:px-7">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    (integration !== null &&
                                                        !formIsDirty)
                                                }
                                                className="w-full sm:w-auto"
                                                data-test="save-email-provider"
                                            >
                                                {processing ? (
                                                    <Spinner data-icon="inline-start" />
                                                ) : null}
                                                {integration
                                                    ? 'Save changes'
                                                    : 'Connect provider'}
                                            </Button>
                                        </div>
                                    </SettingsPanel>
                                </>
                            );
                        }}
                    </Form>
                ) : null}
            </div>

            {integration ? (
                <Dialog open={testOpen} onOpenChange={setTestOpen}>
                    <DialogContent>
                        <Form
                            {...testConnection.form({
                                team: team.slug,
                                emailIntegration: integration.uuid,
                            })}
                            className="space-y-6"
                            errorBag="emailProviderTest"
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            onSuccess={() => setTestOpen(false)}
                            onError={() => focusFirstInvalidField()}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Test email delivery
                                        </DialogTitle>
                                        <DialogDescription>
                                            Use addresses accepted by your
                                            provider. This proves the
                                            credentials and transport only; it
                                            does not register a workspace
                                            sender.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <FieldGroup>
                                        <Field
                                            data-invalid={Boolean(errors.from)}
                                        >
                                            <FieldLabel htmlFor="delivery-test-from">
                                                From address
                                            </FieldLabel>
                                            <Input
                                                id="delivery-test-from"
                                                name="from"
                                                type="email"
                                                required
                                                placeholder="delivery-test@example.com"
                                                autoComplete="email"
                                            />
                                            <FieldDescription>
                                                For SES, this identity must
                                                already be allowed in AWS.
                                            </FieldDescription>
                                            <FieldError>
                                                {errors.from}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={Boolean(errors.to)}
                                        >
                                            <FieldLabel htmlFor="delivery-test-to">
                                                To address
                                            </FieldLabel>
                                            <Input
                                                id="delivery-test-to"
                                                name="to"
                                                type="email"
                                                required
                                                placeholder="you@example.com"
                                                autoComplete="email"
                                            />
                                            <FieldError>{errors.to}</FieldError>
                                        </Field>
                                    </FieldGroup>
                                    <DialogFooter>
                                        <DialogClose
                                            render={
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                />
                                            }
                                        >
                                            Cancel
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="send-email-provider-test"
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : null}
                                            Send test
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            ) : null}

            {integration ? (
                <AlertDialog
                    open={disconnectOpen}
                    onOpenChange={setDisconnectOpen}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Disconnect email delivery?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Campaign and transactional sending will stop.
                                Existing sender addresses remain registered, but
                                each must be tested again after a delivery
                                connection is added.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <Form
                                {...destroy.form.delete({
                                    team: team.slug,
                                    emailIntegration: integration.uuid,
                                })}
                            >
                                {({ processing }) => (
                                    <AlertDialogAction
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : null}
                                        Disconnect
                                    </AlertDialogAction>
                                )}
                            </Form>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            ) : null}
        </>
    );
}

function SesLimitsSummary({ limits }: { limits: SesAccountLimits | null }) {
    if (limits === null) {
        return (
            <span className="text-muted-foreground">
                Available once the connection is tested
            </span>
        );
    }

    if (!limits.available) {
        return <span className="text-muted-foreground">{limits.reason}</span>;
    }

    return (
        <span className="flex flex-col gap-1">
            <span className="tabular-nums">
                {limits.sent_last_24_hours.toLocaleString()} of{' '}
                {limits.max_24_hour_send.toLocaleString()} sent in the last 24
                hours · up to {limits.max_send_rate.toLocaleString()} per second
            </span>
            {limits.sandbox ? (
                <span className="text-warning">
                    This account is in the SES sandbox, so it can only send to
                    verified addresses. Request production access in the AWS
                    console before sending campaigns.
                </span>
            ) : null}
        </span>
    );
}
