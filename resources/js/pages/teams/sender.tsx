import {
    ArrowDown01Icon,
    Copy01Icon,
    Delete02Icon,
    Edit03Icon,
    Globe02Icon,
    MailAtSign02Icon,
    MoreHorizontalIcon,
    Refresh03Icon,
    Tick02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toast } from '@/components/ui/toast';
import { useClipboard } from '@/hooks/use-clipboard';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import { edit as editEmailDelivery } from '@/routes/teams/email-provider';
import { destroy, store, update } from '@/routes/teams/sender';
import { update as makeDefault } from '@/routes/teams/sender/default';
import {
    destroy as destroyDomain,
    store as storeDomain,
    verify as verifyDomain,
} from '@/routes/teams/sender/domains';
import { store as resendVerification } from '@/routes/teams/sender/verification';
import type { Team, TeamSender, TeamSenderDomain } from '@/types';

type Props = {
    team: Team;
    senders: TeamSender[];
    domains: TeamSenderDomain[];
    delivery: {
        configured: boolean;
        verified: boolean;
        trust_provider_senders: boolean;
    };
    canManage: boolean;
};

export default function TeamSenderSettingsPage({
    team,
    senders,
    domains,
    delivery,
    canManage,
}: Props) {
    const [addSenderOpen, setAddSenderOpen] = useState(false);
    const [addDomainOpen, setAddDomainOpen] = useState(false);
    const [senderToEdit, setSenderToEdit] = useState<TeamSender | null>(null);
    const [senderToRemove, setSenderToRemove] = useState<TeamSender | null>(
        null,
    );
    const [domainToRemove, setDomainToRemove] =
        useState<TeamSenderDomain | null>(null);

    return (
        <>
            <Head title={`Sender · ${team.name}`} />

            <div className="flex flex-col gap-8" data-test="sender-page">
                <SettingsPageHeader
                    title="Sender"
                    description="Register exact From addresses after the workspace delivery connection has been tested."
                />
                {!delivery.verified ? (
                    <Alert data-test="sender-delivery-required">
                        <HugeiconsIcon
                            icon={MailAtSign02Icon}
                            aria-hidden="true"
                        />
                        <AlertTitle>
                            {delivery.configured
                                ? 'Verify email delivery first'
                                : 'Connect email delivery first'}
                        </AlertTitle>
                        <AlertDescription>
                            Sender verification uses the workspace delivery
                            connection. Test that connection before adding or
                            retesting a sender.{' '}
                            <Link
                                href={editEmailDelivery(team.slug)}
                                className="font-medium text-foreground underline underline-offset-4"
                            >
                                Manage email delivery
                            </Link>
                        </AlertDescription>
                    </Alert>
                ) : null}
                {delivery.verified && delivery.trust_provider_senders ? (
                    <Alert data-test="sender-provider-trust-enabled">
                        <HugeiconsIcon icon={Tick02Icon} aria-hidden="true" />
                        <AlertTitle>Provider trust enabled</AlertTitle>
                        <AlertDescription>
                            Registered sender addresses on the same domain as
                            the last tested From address are authorized without
                            a Maildun DNS record or mailbox verification.
                        </AlertDescription>
                    </Alert>
                ) : null}
                <SettingsPanel
                    variant="inset"
                    title="Sender domains"
                    description="Verify a domain with DNS to authorize registered addresses such as noreply without requiring a mailbox. This is optional when provider trust is enabled."
                    actions={
                        canManage && domains.length > 0 ? (
                            <Button
                                type="button"
                                data-test="add-sender-domain-button"
                                disabled={!delivery.verified}
                                onClick={() => setAddDomainOpen(true)}
                            >
                                Add domain
                            </Button>
                        ) : undefined
                    }
                >
                    {domains.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon icon={Globe02Icon} />
                                </EmptyMedia>
                                <EmptyTitle>No sender domains yet</EmptyTitle>
                                <EmptyDescription>
                                    Add a domain and publish its TXT record to
                                    use addresses that cannot receive
                                    verification mail.
                                </EmptyDescription>
                            </EmptyHeader>
                            {canManage ? (
                                <EmptyContent>
                                    <Button
                                        type="button"
                                        data-test="add-sender-domain-button"
                                        disabled={!delivery.verified}
                                        onClick={() => setAddDomainOpen(true)}
                                    >
                                        Add domain
                                    </Button>
                                </EmptyContent>
                            ) : null}
                        </Empty>
                    ) : (
                        <div className="divide-y">
                            {domains.map((senderDomain) => (
                                <SenderDomainRow
                                    key={senderDomain.uuid}
                                    team={team}
                                    senderDomain={senderDomain}
                                    canManage={canManage}
                                    canVerify={delivery.verified}
                                    defaultOpen={
                                        !delivery.trust_provider_senders
                                    }
                                    onRemove={() =>
                                        setDomainToRemove(senderDomain)
                                    }
                                />
                            ))}
                        </div>
                    )}
                </SettingsPanel>
                <SettingsPanel
                    variant="inset"
                    title="Sender details"
                    description="Only verified senders can be selected as the workspace default. Exact registered addresses can be authorized by email or a verified domain."
                    actions={
                        canManage && senders.length > 0 ? (
                            <Button
                                type="button"
                                data-test="add-sender-button"
                                disabled={!delivery.verified}
                                onClick={() => setAddSenderOpen(true)}
                            >
                                Add sender
                            </Button>
                        ) : undefined
                    }
                >
                    {senders.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon icon={MailAtSign02Icon} />
                                </EmptyMedia>
                                <EmptyTitle>No senders yet</EmptyTitle>
                                <EmptyDescription>
                                    {canManage && delivery.verified
                                        ? 'Add an address. A verified domain authorizes it immediately; otherwise we send a verification email.'
                                        : 'Verify email delivery before adding sender addresses.'}
                                </EmptyDescription>
                            </EmptyHeader>
                            {canManage && (
                                <EmptyContent>
                                    <Button
                                        type="button"
                                        data-test="add-sender-button"
                                        disabled={!delivery.verified}
                                        onClick={() => setAddSenderOpen(true)}
                                    >
                                        Add sender
                                    </Button>
                                </EmptyContent>
                            )}
                        </Empty>
                    ) : (
                        <div className="p-3 sm:p-4">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="h-12 px-5">
                                            Sender
                                        </TableHead>
                                        <TableHead className="hidden h-12 px-5 sm:table-cell">
                                            Reply-to
                                        </TableHead>
                                        <TableHead className="h-12 px-5">
                                            Status
                                        </TableHead>
                                        {canManage && (
                                            <TableHead className="h-12 w-[1%] px-5 text-right">
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {senders.map((sender) => (
                                        <TableRow
                                            key={sender.uuid}
                                            data-test="sender-row"
                                            className="h-20"
                                        >
                                            <TableCell className="max-w-0 px-5 py-4">
                                                <div className="flex min-w-56 items-center gap-3">
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">
                                                            {sender.name ||
                                                                sender.email}
                                                        </p>
                                                        {sender.name ? (
                                                            <p className="truncate text-sm text-muted-foreground">
                                                                {sender.email}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    {sender.is_default ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="shrink-0"
                                                            data-test="sender-default-badge"
                                                        >
                                                            Default
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell className="hidden max-w-0 px-5 py-4 text-muted-foreground sm:table-cell">
                                                <span className="block min-w-40 truncate">
                                                    {sender.reply_to ?? '—'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="w-36 px-5 py-4">
                                                <Badge
                                                    variant={
                                                        sender.is_verified
                                                            ? 'success'
                                                            : sender.was_verified
                                                              ? 'destructive'
                                                              : 'orange'
                                                    }
                                                    data-test="sender-status-badge"
                                                >
                                                    {sender.is_verified
                                                        ? 'Verified'
                                                        : sender.was_verified
                                                          ? 'Retest required'
                                                          : 'Pending'}
                                                </Badge>
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="w-[1%] px-5 py-4 text-right">
                                                    <SenderActions
                                                        team={team}
                                                        sender={sender}
                                                        canVerify={
                                                            delivery.verified
                                                        }
                                                        onEdit={() =>
                                                            setSenderToEdit(
                                                                sender,
                                                            )
                                                        }
                                                        onRemove={() =>
                                                            setSenderToRemove(
                                                                sender,
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </SettingsPanel>
            </div>

            {canManage ? (
                <>
                    <AddSenderDomainDialog
                        team={team}
                        open={addDomainOpen}
                        onOpenChange={setAddDomainOpen}
                    />

                    <AddSenderDialog
                        team={team}
                        open={addSenderOpen}
                        onOpenChange={setAddSenderOpen}
                    />

                    {senderToEdit ? (
                        <EditSenderDialog
                            key={senderToEdit.uuid}
                            team={team}
                            sender={senderToEdit}
                            open
                            onOpenChange={(open) => {
                                if (!open) {
                                    setSenderToEdit(null);
                                }
                            }}
                        />
                    ) : null}

                    <RemoveSenderDialog
                        team={team}
                        sender={senderToRemove}
                        open={senderToRemove !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setSenderToRemove(null);
                            }
                        }}
                    />

                    <RemoveSenderDomainDialog
                        team={team}
                        senderDomain={domainToRemove}
                        open={domainToRemove !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setDomainToRemove(null);
                            }
                        }}
                    />
                </>
            ) : null}
        </>
    );
}

function SenderDomainRow({
    team,
    senderDomain,
    canManage,
    canVerify,
    defaultOpen,
    onRemove,
}: {
    team: Team;
    senderDomain: TeamSenderDomain;
    canManage: boolean;
    canVerify: boolean;
    defaultOpen: boolean;
    onRemove: () => void;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const [copiedText, copy] = useClipboard();

    const copyDnsValue = async (value: string) => {
        const copied = await copy(value);

        toast.add({
            type: copied ? 'success' : 'error',
            title: copied ? 'Copied to clipboard.' : 'Could not copy value.',
        });
    };

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            data-test="sender-domain-row"
        >
            <div className="flex items-center transition-colors hover:bg-muted/50">
                <CollapsibleTrigger
                    render={<button type="button" />}
                    className="flex min-w-0 flex-1 items-center gap-3 px-5 py-5 text-left"
                    data-test="toggle-sender-domain"
                    aria-label={`${open ? 'Hide' : 'Show'} DNS records for ${senderDomain.domain}`}
                >
                    <HugeiconsIcon
                        icon={ArrowDown01Icon}
                        className={
                            open
                                ? 'size-4 shrink-0 rotate-180 text-muted-foreground transition-transform duration-200 motion-reduce:transition-none'
                                : 'size-4 shrink-0 text-muted-foreground transition-transform duration-200 motion-reduce:transition-none'
                        }
                        aria-hidden="true"
                    />
                    <span className="min-w-0 flex-1 truncate font-medium">
                        {senderDomain.domain}
                    </span>
                    <Badge
                        variant={
                            senderDomain.is_verified
                                ? 'success'
                                : senderDomain.was_verified
                                  ? 'destructive'
                                  : 'orange'
                        }
                        data-test="sender-domain-status"
                    >
                        {senderDomain.is_verified
                            ? 'Verified'
                            : senderDomain.was_verified
                              ? 'Retest required'
                              : 'Pending'}
                    </Badge>
                </CollapsibleTrigger>

                {canManage ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    type="button"
                                    size="icon-sm"
                                    variant="ghost"
                                    className="mr-3 shrink-0"
                                    data-test="sender-domain-actions"
                                    aria-label={`Actions for ${senderDomain.domain}`}
                                />
                            }
                        >
                            <HugeiconsIcon icon={MoreHorizontalIcon} />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-max">
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    data-test="verify-sender-domain-button"
                                    disabled={!canVerify}
                                    onClick={() =>
                                        router.visit(
                                            verifyDomain({
                                                team: team.slug,
                                                teamSenderDomain:
                                                    senderDomain.uuid,
                                            }).url,
                                            {
                                                method: 'post',
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                >
                                    <HugeiconsIcon icon={Refresh03Icon} />
                                    Check DNS
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    variant="destructive"
                                    data-test="remove-sender-domain-button"
                                    onClick={onRemove}
                                >
                                    <HugeiconsIcon icon={Delete02Icon} />
                                    Delete domain
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </div>

            <CollapsibleContent className="h-(--collapsible-panel-height) overflow-hidden transition-[height,opacity] duration-300 ease-out data-ending-style:h-0 data-ending-style:opacity-0 data-starting-style:h-0 data-starting-style:opacity-0 motion-reduce:transition-none">
                <div className="grid gap-3 border-t px-5 py-4 text-sm">
                    <DnsRecordValue
                        label="TXT name"
                        value={senderDomain.dns_record_name}
                        copied={copiedText === senderDomain.dns_record_name}
                        onCopy={() =>
                            void copyDnsValue(senderDomain.dns_record_name)
                        }
                    />
                    <DnsRecordValue
                        label="TXT value"
                        value={senderDomain.dns_record_value}
                        copied={copiedText === senderDomain.dns_record_value}
                        onCopy={() =>
                            void copyDnsValue(senderDomain.dns_record_value)
                        }
                    />
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

function DnsRecordValue({
    label,
    value,
    copied,
    onCopy,
}: {
    label: string;
    value: string;
    copied: boolean;
    onCopy: () => void;
}) {
    return (
        <div className="grid min-w-0 gap-1 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center">
            <span className="text-muted-foreground">{label}</span>
            <div className="flex min-w-0 items-center gap-1 rounded-md bg-muted px-2 py-1.5">
                <code className="min-w-0 flex-1 truncate text-xs">{value}</code>
                <Button
                    type="button"
                    size="icon-xs"
                    variant="ghost"
                    aria-label={`Copy ${label.toLowerCase()}`}
                    onClick={onCopy}
                >
                    <HugeiconsIcon icon={copied ? Tick02Icon : Copy01Icon} />
                </Button>
            </div>
        </div>
    );
}

function AddSenderDomainDialog({
    team,
    open,
    onOpenChange,
}: {
    team: Team;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    className="space-y-6"
                    {...storeDomain.form.post(team.slug)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    onError={() => focusFirstInvalidField()}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Add sender domain</DialogTitle>
                                <DialogDescription>
                                    Maildun will generate a TXT record that
                                    proves you control this domain.
                                </DialogDescription>
                            </DialogHeader>

                            <Field data-invalid={Boolean(errors.domain)}>
                                <FieldLabel htmlFor="sender-domain">
                                    Domain
                                </FieldLabel>
                                <Input
                                    id="sender-domain"
                                    name="domain"
                                    autoFocus
                                    required
                                    maxLength={253}
                                    placeholder="example.com"
                                />
                                <FieldDescription>
                                    Enter only the domain, without https:// or
                                    an email address.
                                </FieldDescription>
                                <FieldError>{errors.domain}</FieldError>
                            </Field>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={<Button variant="secondary" />}
                                >
                                    Cancel
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : null}
                                    Add domain
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RemoveSenderDomainDialog({
    team,
    senderDomain,
    open,
    onOpenChange,
}: {
    team: Team;
    senderDomain: TeamSenderDomain | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);

    const removeDomain = () => {
        if (!senderDomain) {
            return;
        }

        router.visit(
            destroyDomain({
                team: team.slug,
                teamSenderDomain: senderDomain.uuid,
            }).url,
            {
                method: 'delete',
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove sender domain</DialogTitle>
                    <DialogDescription>
                        Senders authorized through{' '}
                        <strong>{senderDomain?.domain}</strong> will return to
                        pending and cannot send until they are verified again.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={removeDomain}
                    >
                        {processing ? (
                            <Spinner data-icon="inline-start" />
                        ) : null}
                        Remove domain
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function SenderActions({
    team,
    sender,
    canVerify,
    onEdit,
    onRemove,
}: {
    team: Team;
    sender: TeamSender;
    canVerify: boolean;
    onEdit: () => void;
    onRemove: () => void;
}) {
    const label = sender.name || sender.email;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        size="icon"
                        variant="ghost"
                        data-test="sender-actions"
                        aria-label={`Actions for ${label}`}
                    />
                }
            >
                <HugeiconsIcon icon={MoreHorizontalIcon} />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-max">
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        data-test="edit-sender-button"
                        onClick={onEdit}
                    >
                        <HugeiconsIcon icon={Edit03Icon} />
                        Edit
                    </DropdownMenuItem>
                    {!sender.is_verified ? (
                        <DropdownMenuItem
                            data-test="resend-sender-verification-button"
                            disabled={!canVerify}
                            onClick={() =>
                                router.visit(
                                    resendVerification({
                                        team: team.slug,
                                        teamSender: sender.uuid,
                                    }).url,
                                    { method: 'post', preserveScroll: true },
                                )
                            }
                        >
                            <HugeiconsIcon icon={Refresh03Icon} />
                            Resend verification
                        </DropdownMenuItem>
                    ) : null}
                    {sender.is_verified && !sender.is_default ? (
                        <DropdownMenuItem
                            data-test="make-default-sender-button"
                            onClick={() =>
                                router.visit(
                                    makeDefault({
                                        team: team.slug,
                                        teamSender: sender.uuid,
                                    }).url,
                                    { method: 'patch', preserveScroll: true },
                                )
                            }
                        >
                            <HugeiconsIcon icon={Tick02Icon} />
                            Use as default
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        variant="destructive"
                        data-test="remove-sender-button"
                        onClick={onRemove}
                    >
                        <HugeiconsIcon icon={Delete02Icon} />
                        Remove
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function AddSenderDialog({
    team,
    open,
    onOpenChange,
}: {
    team: Team;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    className="space-y-6"
                    {...store.form.post(team.slug)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    onError={() => focusFirstInvalidField()}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Add sender</DialogTitle>
                                <DialogDescription>
                                    A verified domain authorizes this address
                                    immediately. Otherwise, we send it a
                                    verification email.
                                </DialogDescription>
                            </DialogHeader>

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="sender-name">
                                        From name
                                    </FieldLabel>
                                    <Input
                                        id="sender-name"
                                        name="name"
                                        autoFocus
                                        maxLength={255}
                                        placeholder="Acme Newsletter"
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field data-invalid={Boolean(errors.email)}>
                                    <FieldLabel htmlFor="sender-email">
                                        From address
                                    </FieldLabel>
                                    <Input
                                        id="sender-email"
                                        name="email"
                                        type="email"
                                        required
                                        maxLength={255}
                                        placeholder="newsletter@example.com"
                                    />
                                    <FieldDescription>
                                        This address cannot be changed after it
                                        is added. Add a new sender when you need
                                        a different address.
                                    </FieldDescription>
                                    <FieldError>{errors.email}</FieldError>
                                </Field>
                                <Field data-invalid={Boolean(errors.reply_to)}>
                                    <FieldLabel htmlFor="sender-reply-to">
                                        Reply-to
                                    </FieldLabel>
                                    <Input
                                        id="sender-reply-to"
                                        name="reply_to"
                                        type="email"
                                        maxLength={255}
                                        placeholder="replies@example.com"
                                    />
                                    <FieldDescription>
                                        Optional. Leave blank to reply to the
                                        From address.
                                    </FieldDescription>
                                    <FieldError>{errors.reply_to}</FieldError>
                                </Field>
                            </FieldGroup>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={<Button variant="secondary" />}
                                >
                                    Cancel
                                </DialogClose>
                                <Button
                                    type="submit"
                                    data-test="add-sender-submit"
                                    disabled={processing}
                                >
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Add sender
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditSenderDialog({
    team,
    sender,
    open,
    onOpenChange,
}: {
    team: Team;
    sender: TeamSender;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={`${sender.uuid}:${String(open)}`}
                    className="space-y-6"
                    {...update.form.patch({
                        team: team.slug,
                        teamSender: sender.uuid,
                    })}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                    onSuccess={() => {
                        toast.add({ type: 'success', title: 'Changes saved.' });
                        onOpenChange(false);
                    }}
                    onError={() => focusFirstInvalidField()}
                >
                    {({ errors, processing, isDirty }) => (
                        <>
                            <UnsavedChangesGuard isDirty={isDirty} />
                            <DialogHeader>
                                <DialogTitle>Edit sender</DialogTitle>
                                <DialogDescription>
                                    Update the sender name or reply-to address.
                                </DialogDescription>
                            </DialogHeader>

                            {!sender.is_verified ? (
                                <Alert>
                                    <HugeiconsIcon
                                        icon={MailAtSign02Icon}
                                        aria-hidden="true"
                                    />
                                    <AlertTitle>Verify this address</AlertTitle>
                                    <AlertDescription>
                                        {sender.verification_sent_at
                                            ? 'Open the latest verification email, or send another one from the row actions.'
                                            : 'The verification email is waiting in the queue.'}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel
                                        htmlFor={`sender-${sender.uuid}-name`}
                                    >
                                        From name
                                    </FieldLabel>
                                    <Input
                                        id={`sender-${sender.uuid}-name`}
                                        name="name"
                                        autoFocus
                                        maxLength={255}
                                        placeholder="Acme Newsletter"
                                        defaultValue={sender.name ?? ''}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel
                                        htmlFor={`sender-${sender.uuid}-email`}
                                    >
                                        From address
                                    </FieldLabel>
                                    <Input
                                        id={`sender-${sender.uuid}-email`}
                                        type="email"
                                        value={sender.email}
                                        disabled
                                        placeholder="newsletter@example.com"
                                    />
                                    <FieldDescription>
                                        Addresses are immutable. Add another
                                        sender to use a different address.
                                    </FieldDescription>
                                </Field>
                                <Field data-invalid={Boolean(errors.reply_to)}>
                                    <FieldLabel
                                        htmlFor={`sender-${sender.uuid}-reply-to`}
                                    >
                                        Reply-to
                                    </FieldLabel>
                                    <Input
                                        id={`sender-${sender.uuid}-reply-to`}
                                        name="reply_to"
                                        type="email"
                                        maxLength={255}
                                        placeholder="replies@example.com"
                                        defaultValue={sender.reply_to ?? ''}
                                    />
                                    <FieldError>{errors.reply_to}</FieldError>
                                </Field>
                            </FieldGroup>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={<Button variant="secondary" />}
                                >
                                    Cancel
                                </DialogClose>
                                <Button
                                    type="submit"
                                    data-test="save-sender-settings"
                                    disabled={processing || !isDirty}
                                >
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RemoveSenderDialog({
    team,
    sender,
    open,
    onOpenChange,
}: {
    team: Team;
    sender: TeamSender | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);

    const removeSender = () => {
        if (!sender) {
            return;
        }

        router.visit(
            destroy({ team: team.slug, teamSender: sender.uuid }).url,
            {
                method: 'delete',
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove sender</DialogTitle>
                    <DialogDescription>
                        <strong>{sender?.email}</strong> will no longer be
                        available to send from.
                        {sender?.is_default
                            ? ' It is the workspace default, so sending pauses until another verified sender is selected.'
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>
                    <Button
                        variant="destructive"
                        data-test="remove-sender-confirm"
                        disabled={processing}
                        onClick={removeSender}
                    >
                        Remove sender
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
