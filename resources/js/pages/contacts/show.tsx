import {
    Add01Icon,
    ArrowDown01Icon,
    Building06Icon,
    Calendar01Icon,
    Delete02Icon,
    Edit03Icon,
    InformationCircleIcon,
    Mail01Icon,
    MailOpen01Icon,
    MailReceive01Icon,
    MoreHorizontalIcon,
    MouseLeftClick01Icon,
    Tag01Icon,
    UserGroupIcon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { ContactDialog } from '@/components/contact-dialog';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { formatRelativeTime } from '@/lib/format';
import { tagBadgeVariant } from '@/lib/tags';
import { cn } from '@/lib/utils';
import { show as showAudience } from '@/routes/audiences';
import { resubscribe, unsubscribe } from '@/routes/audiences/subscribers';
import { edit as editAutomation } from '@/routes/automations';
import { show as showCompany } from '@/routes/companies';
import { destroy } from '@/routes/contacts';
import {
    destroy as removeAudience,
    store as addAudience,
} from '@/routes/contacts/audiences';
import { show as showCampaign } from '@/routes/emails';
import type { Tag } from '@/types/audiences';
import type {
    CompanyOption,
    Contact,
    ContactActivity,
    ContactAttribute,
    ContactAutomation,
    ContactMembership,
} from '@/types/contacts';

type Props = {
    contact: Contact;
    activity: ContactActivity;
    automations: ContactAutomation[];
    selectedAudienceUuid: string | null;
    companies: CompanyOption[];
    audiences: CompanyOption[];
    tags: Tag[];
    canManage: boolean;
};

export default function ContactShow({
    contact,
    activity,
    automations,
    selectedAudienceUuid,
    companies,
    audiences,
    tags,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [membershipToResubscribe, setMembershipToResubscribe] =
        useState<ContactMembership | null>(null);
    const [openMemberships, setOpenMemberships] = useState<string[]>(() =>
        initialOpenMemberships(contact.memberships, selectedAudienceUuid),
    );
    const [consentConfirmed, setConsentConfirmed] = useState(false);
    const addMembership = useForm({
        audience_uuid: '',
        consent_confirmed: true,
    });
    const deleteContact = useForm({ confirmation: '' });

    const availableAudiences = useMemo(
        () =>
            audiences.filter(
                (audience) =>
                    !contact.memberships.some(
                        (membership) =>
                            membership.audience.uuid === audience.uuid,
                    ),
            ),
        [audiences, contact.memberships],
    );

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, contact.uuid];
    const focusedAudience =
        contact.memberships.find(
            (membership) => membership.audience.uuid === selectedAudienceUuid,
        )?.audience ?? null;

    const membershipRouteArgs = (
        membership: ContactMembership,
    ): [string, string, string] => [
        currentTeam.slug,
        membership.audience.uuid,
        membership.uuid,
    ];

    const submitMembership = (event: React.FormEvent): void => {
        event.preventDefault();
        addMembership.post(addAudience.url(routeArgs), {
            preserveScroll: true,
            onSuccess: () => addMembership.reset(),
        });
    };

    const toggleMembership = (membershipUuid: string, open: boolean): void => {
        setOpenMemberships((current) =>
            open
                ? [...current, membershipUuid]
                : current.filter((uuid) => uuid !== membershipUuid),
        );
    };

    const removeMembership = (membershipUuid: string): void => {
        router.delete(
            removeAudience.url([
                currentTeam.slug,
                contact.uuid,
                membershipUuid,
            ]),
            { preserveScroll: true },
        );
    };

    const unsubscribeMembership = (membership: ContactMembership): void => {
        router.patch(
            unsubscribe.url(membershipRouteArgs(membership)),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const confirmResubscribe = (): void => {
        if (!membershipToResubscribe) {
            return;
        }

        router.patch(
            resubscribe.url(membershipRouteArgs(membershipToResubscribe)),
            { consent_confirmed: true },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setConsentConfirmed(false);
                    setMembershipToResubscribe(null);
                },
            },
        );
    };

    const confirmDelete = (): void => {
        deleteContact.delete(destroy.url(routeArgs));
    };

    return (
        <>
            <Head
                title={
                    [contact.first_name, contact.last_name]
                        .filter(Boolean)
                        .join(' ') || contact.email
                }
            />
            <div
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-8"
                data-test="contact-profile"
            >
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex items-start gap-3">
                        <Avatar size="lg">
                            <AvatarImage src={contact.avatar} alt="" />
                            <AvatarFallback>
                                {(contact.first_name ?? contact.email)
                                    .charAt(0)
                                    .toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {[contact.first_name, contact.last_name]
                                        .filter(Boolean)
                                        .join(' ') || contact.email}
                                </h1>
                                {focusedAudience ? (
                                    <Badge variant="secondary">
                                        {focusedAudience.name}
                                    </Badge>
                                ) : null}
                            </div>
                            <p className="truncate text-sm text-muted-foreground">
                                {contact.email}
                            </p>
                        </div>
                    </div>
                    {canManage ? (
                        <div className="flex shrink-0 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={Edit03Icon}
                                    data-icon="inline-start"
                                />
                                Edit
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => setDeleteOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={Delete02Icon}
                                    data-icon="inline-start"
                                />
                                Delete
                            </Button>
                        </div>
                    ) : null}
                </div>

                <section className="flex flex-col gap-3">
                    <h2 className="font-heading text-base font-medium">
                        Activity
                    </h2>
                    <Card className="gap-0 py-0">
                        <PropertyList>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={UserGroupIcon}
                                            />
                                        }
                                    >
                                        Audiences
                                    </IconLabel>
                                }
                                href="#audiences"
                            >
                                {activity.audiences.toLocaleString()}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={MailReceive01Icon}
                                            />
                                        }
                                    >
                                        Emails received
                                    </IconLabel>
                                }
                                href="#received-emails"
                            >
                                {activity.received.toLocaleString()}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={MailOpen01Icon}
                                            />
                                        }
                                    >
                                        Opened
                                    </IconLabel>
                                }
                                href="#received-emails"
                            >
                                {activity.opened.toLocaleString()}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={MouseLeftClick01Icon}
                                            />
                                        }
                                    >
                                        Clicked
                                    </IconLabel>
                                }
                                href="#received-emails"
                            >
                                {activity.clicked.toLocaleString()}
                            </PropertyRow>
                        </PropertyList>
                        <CardFooter className="justify-between gap-3 text-muted-foreground">
                            <p>{membershipActivityDetail(activity)}</p>
                        </CardFooter>
                    </Card>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="font-heading text-base font-medium">
                        Details
                    </h2>
                    <Card className="gap-0 py-0">
                        <PropertyList>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon icon={Mail01Icon} />
                                        }
                                    >
                                        Email
                                    </IconLabel>
                                }
                            >
                                {contact.email}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={<HugeiconsIcon icon={UserIcon} />}
                                    >
                                        First name
                                    </IconLabel>
                                }
                            >
                                {contact.first_name || '—'}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={<HugeiconsIcon icon={UserIcon} />}
                                    >
                                        Last name
                                    </IconLabel>
                                }
                            >
                                {contact.last_name || '—'}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={Building06Icon}
                                            />
                                        }
                                    >
                                        Company
                                    </IconLabel>
                                }
                            >
                                {contact.company ? (
                                    <Link
                                        href={showCompany.url([
                                            currentTeam.slug,
                                            contact.company.uuid,
                                        ])}
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {contact.company.name}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </PropertyRow>
                            <PropertyRow label="Company assignment">
                                {contact.company_assignment_mode === 'automatic'
                                    ? 'Matched automatically'
                                    : 'Set manually'}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon icon={Tag01Icon} />
                                        }
                                    >
                                        Tags
                                    </IconLabel>
                                }
                            >
                                {contact.tags.length > 0 ? (
                                    <span className="flex flex-wrap justify-end gap-1">
                                        {contact.tags.map((tag) => (
                                            <Badge
                                                key={tag.uuid}
                                                variant={tagBadgeVariant(
                                                    tag.color,
                                                )}
                                            >
                                                {tag.name}
                                            </Badge>
                                        ))}
                                    </span>
                                ) : (
                                    '—'
                                )}
                            </PropertyRow>
                            <PropertyRow
                                label={
                                    <IconLabel
                                        icon={
                                            <HugeiconsIcon
                                                icon={Calendar01Icon}
                                            />
                                        }
                                    >
                                        Added
                                    </IconLabel>
                                }
                                title={absoluteTime(contact.created_at)}
                            >
                                {contact.created_at
                                    ? formatRelativeTime(contact.created_at)
                                    : '—'}
                            </PropertyRow>
                        </PropertyList>
                    </Card>
                </section>

                <section id="audiences" className="flex flex-col gap-3">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <h2 className="font-heading text-base font-medium">
                                Audiences
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Subscription and consent stay scoped to each
                                audience.
                            </p>
                        </div>
                        {canManage && availableAudiences.length ? (
                            <form
                                onSubmit={submitMembership}
                                className="flex gap-2"
                            >
                                <Select
                                    value={
                                        addMembership.data.audience_uuid || null
                                    }
                                    onValueChange={(value) =>
                                        addMembership.setData(
                                            'audience_uuid',
                                            (value as string | null) ?? '',
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-52">
                                        <SelectValue placeholder="Choose audience" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {availableAudiences.map(
                                                (audience) => (
                                                    <SelectItem
                                                        key={audience.uuid}
                                                        value={audience.uuid}
                                                    >
                                                        {audience.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="submit"
                                    disabled={
                                        !addMembership.data.audience_uuid ||
                                        addMembership.processing
                                    }
                                >
                                    {addMembership.processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <HugeiconsIcon
                                            icon={Add01Icon}
                                            data-icon="inline-start"
                                        />
                                    )}
                                    Add
                                </Button>
                            </form>
                        ) : null}
                    </div>
                    <Card className="gap-0 py-0">
                        {contact.memberships.length ? (
                            <div className="divide-y">
                                {contact.memberships.map((membership) => (
                                    <MembershipPanel
                                        key={membership.uuid}
                                        membership={membership}
                                        teamSlug={currentTeam.slug}
                                        open={openMemberships.includes(
                                            membership.uuid,
                                        )}
                                        onOpenChange={(open) =>
                                            toggleMembership(
                                                membership.uuid,
                                                open,
                                            )
                                        }
                                        highlighted={
                                            membership.audience.uuid ===
                                            selectedAudienceUuid
                                        }
                                        canManage={canManage}
                                        onUnsubscribe={() =>
                                            unsubscribeMembership(membership)
                                        }
                                        onResubscribe={() =>
                                            setMembershipToResubscribe(
                                                membership,
                                            )
                                        }
                                        onRemove={() =>
                                            removeMembership(membership.uuid)
                                        }
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="px-(--card-spacing) py-8 text-sm text-muted-foreground">
                                This contact has not joined an audience.
                            </p>
                        )}
                    </Card>
                </section>

                <section id="received-emails" className="flex flex-col gap-3">
                    <h2 className="font-heading text-base font-medium">
                        Received emails
                    </h2>
                    <Card className="gap-0 py-0">
                        {contact.deliveries.length ? (
                            <PropertyList>
                                {contact.deliveries.map((delivery) => (
                                    <div
                                        key={delivery.uuid}
                                        className="flex items-start justify-between gap-4 px-(--card-spacing) py-3"
                                        data-test="contact-email-row"
                                    >
                                        <HugeiconsIcon
                                            icon={MailReceive01Icon}
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-label="Email received"
                                        />
                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            {delivery.campaign.uuid ? (
                                                <Link
                                                    href={showCampaign.url([
                                                        currentTeam.slug,
                                                        delivery.campaign.uuid,
                                                    ])}
                                                    className="truncate font-medium underline-offset-4 hover:underline"
                                                >
                                                    {delivery.campaign.name}
                                                </Link>
                                            ) : (
                                                <p className="truncate font-medium">
                                                    {delivery.campaign.name}
                                                </p>
                                            )}
                                            <p className="text-sm text-muted-foreground">
                                                {delivery.audience?.name ??
                                                    'No audience'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <Badge variant="outline">
                                                {delivery.status}
                                            </Badge>
                                            <p className="text-xs text-muted-foreground">
                                                {delivery.opens.toLocaleString()}{' '}
                                                opens ·{' '}
                                                {delivery.clicks.toLocaleString()}{' '}
                                                clicks
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {delivery.sent_at
                                                    ? formatRelativeTime(
                                                          delivery.sent_at,
                                                      )
                                                    : 'Not sent'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </PropertyList>
                        ) : (
                            <p className="px-(--card-spacing) py-8 text-sm text-muted-foreground">
                                No campaign deliveries for this contact yet.
                            </p>
                        )}
                    </Card>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="font-heading text-base font-medium">
                        Automations
                    </h2>
                    <Card className="gap-0 py-0">
                        {automations.length ? (
                            <PropertyList>
                                {automations.map((automation) => (
                                    <div
                                        key={automation.uuid}
                                        className="flex items-start justify-between gap-4 px-(--card-spacing) py-3"
                                        data-test="contact-automation-row"
                                    >
                                        <HugeiconsIcon
                                            icon={UserGroupIcon}
                                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                            aria-label="Automation"
                                        />
                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            {automation.automation_uuid ? (
                                                <Link
                                                    href={editAutomation.url([
                                                        currentTeam.slug,
                                                        automation.automation_uuid,
                                                    ])}
                                                    className="truncate font-medium underline-offset-4 hover:underline"
                                                >
                                                    {automation.name}
                                                </Link>
                                            ) : (
                                                <p className="truncate font-medium">
                                                    {automation.name}
                                                </p>
                                            )}
                                            <p className="text-sm text-muted-foreground">
                                                {automation.audience?.name ??
                                                    'No audience'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1">
                                            <Badge variant="outline">
                                                {automation.status}
                                            </Badge>
                                            <p className="text-xs text-muted-foreground">
                                                {automation.completed_at
                                                    ? `Completed ${formatRelativeTime(automation.completed_at)}`
                                                    : automation.started_at
                                                      ? `Started ${formatRelativeTime(automation.started_at)}`
                                                      : 'Not started'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </PropertyList>
                        ) : (
                            <p className="px-(--card-spacing) py-8 text-sm text-muted-foreground">
                                No automation runs for this contact yet.
                            </p>
                        )}
                    </Card>
                </section>
            </div>

            {canManage ? (
                <ContactDialog
                    teamSlug={currentTeam.slug}
                    companies={companies}
                    tags={tags}
                    contact={contact}
                    open={editOpen}
                    onOpenChange={setEditOpen}
                />
            ) : null}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete this contact?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This permanently removes the contact and every
                            audience membership. Past delivery snapshots remain.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <Field
                        data-invalid={Boolean(
                            deleteContact.errors.confirmation,
                        )}
                    >
                        <FieldLabel htmlFor="delete-contact-confirmation">
                            Type {contact.email} to confirm
                        </FieldLabel>
                        <Input
                            id="delete-contact-confirmation"
                            placeholder={contact.email}
                            value={deleteContact.data.confirmation}
                            onChange={(event) =>
                                deleteContact.setData(
                                    'confirmation',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(
                                deleteContact.errors.confirmation,
                            )}
                        />
                        <FieldError>
                            {deleteContact.errors.confirmation}
                        </FieldError>
                    </Field>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            type="button"
                            variant="destructive"
                            disabled={
                                deleteContact.processing ||
                                deleteContact.data.confirmation !==
                                    contact.email
                            }
                            onClick={confirmDelete}
                        >
                            {deleteContact.processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : null}
                            Delete contact
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            <AlertDialog
                open={membershipToResubscribe !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConsentConfirmed(false);
                        setMembershipToResubscribe(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Resubscribe to{' '}
                            {membershipToResubscribe?.audience.name}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Confirm that this contact has agreed to receive
                            marketing email from this audience.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <Field orientation="horizontal">
                        <Checkbox
                            id="contact-resubscribe-consent"
                            checked={consentConfirmed}
                            onCheckedChange={(checked) =>
                                setConsentConfirmed(checked === true)
                            }
                        />
                        <FieldLabel htmlFor="contact-resubscribe-consent">
                            Marketing consent confirmed
                        </FieldLabel>
                    </Field>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            type="button"
                            disabled={!consentConfirmed}
                            onClick={confirmResubscribe}
                        >
                            Resubscribe
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function initialOpenMemberships(
    memberships: ContactMembership[],
    selectedAudienceUuid: string | null,
): string[] {
    const selected = memberships.find(
        (membership) => membership.audience.uuid === selectedAudienceUuid,
    );

    if (selected) {
        return [selected.uuid];
    }

    return memberships.length === 1 ? [memberships[0].uuid] : [];
}

function MembershipPanel({
    membership,
    teamSlug,
    open,
    onOpenChange,
    highlighted,
    canManage,
    onUnsubscribe,
    onResubscribe,
    onRemove,
}: {
    membership: ContactMembership;
    teamSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    highlighted: boolean;
    canManage: boolean;
    onUnsubscribe: () => void;
    onResubscribe: () => void;
    onRemove: () => void;
}) {
    return (
        <Collapsible
            open={open}
            onOpenChange={onOpenChange}
            data-test="contact-membership-row"
        >
            <CollapsibleTrigger
                render={<button type="button" />}
                className={cn(
                    'flex w-full items-center gap-3 px-(--card-spacing) py-3 text-left text-sm transition-colors hover:bg-muted/50',
                    highlighted && 'bg-muted/50',
                )}
            >
                <HugeiconsIcon
                    icon={ArrowDown01Icon}
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform duration-200 motion-reduce:transition-none',
                        open && 'rotate-180',
                    )}
                />
                <span className="min-w-0 flex-1 truncate font-medium">
                    {membership.audience.name}
                </span>
                <Badge
                    variant={
                        membership.status === 'subscribed'
                            ? 'success'
                            : 'secondary'
                    }
                >
                    {membership.status === 'subscribed'
                        ? 'Subscribed'
                        : 'Unsubscribed'}
                </Badge>
                <span
                    className="hidden shrink-0 text-muted-foreground sm:inline"
                    title={absoluteTime(membership.subscribed_at)}
                >
                    {membership.subscribed_at
                        ? formatRelativeTime(membership.subscribed_at)
                        : 'Pending confirmation'}
                </span>
            </CollapsibleTrigger>
            <CollapsibleContent className="h-(--collapsible-panel-height) overflow-hidden transition-[height,opacity] duration-300 ease-out data-ending-style:h-0 data-ending-style:opacity-0 data-starting-style:h-0 data-starting-style:opacity-0 motion-reduce:transition-none">
                <Separator />
                <div className="flex flex-col justify-between gap-3 px-(--card-spacing) py-3 sm:flex-row sm:items-center">
                    <div>
                        <p className="font-medium">
                            {membership.audience.name} membership
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Audience-specific details and consent.
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    size="icon-sm"
                                    variant="ghost"
                                    aria-label={`Actions for ${membership.audience.name}`}
                                />
                            }
                        >
                            <HugeiconsIcon icon={MoreHorizontalIcon} />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    render={
                                        <Link
                                            href={showAudience([
                                                teamSlug,
                                                membership.audience.uuid,
                                            ])}
                                            prefetch
                                        />
                                    }
                                >
                                    View audience
                                </DropdownMenuItem>
                                {membership.can_manage ? (
                                    <DropdownMenuItem
                                        onClick={
                                            membership.status === 'subscribed'
                                                ? onUnsubscribe
                                                : onResubscribe
                                        }
                                    >
                                        {membership.status === 'subscribed'
                                            ? 'Unsubscribe'
                                            : 'Resubscribe'}
                                    </DropdownMenuItem>
                                ) : null}
                                {canManage ? (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={onRemove}
                                    >
                                        Remove
                                    </DropdownMenuItem>
                                ) : null}
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <Separator />
                <NestedPanel
                    title="Membership details"
                    icon={<HugeiconsIcon icon={UserGroupIcon} />}
                >
                    <PropertyList>
                        <PropertyRow label="Status" className="px-3">
                            {membership.status === 'subscribed'
                                ? 'Subscribed'
                                : 'Unsubscribed'}
                        </PropertyRow>
                        <PropertyRow label="Source" className="px-3">
                            <span className="capitalize">
                                {membership.source_form?.name ??
                                    membership.source}
                            </span>
                        </PropertyRow>
                        <PropertyRow
                            label="Subscribed"
                            title={absoluteTime(membership.subscribed_at)}
                            className="px-3"
                        >
                            {membership.subscribed_at
                                ? formatRelativeTime(membership.subscribed_at)
                                : 'Pending confirmation'}
                        </PropertyRow>
                        {membership.unsubscribed_at ? (
                            <PropertyRow
                                label="Unsubscribed"
                                title={absoluteTime(membership.unsubscribed_at)}
                                className="px-3"
                            >
                                {formatRelativeTime(membership.unsubscribed_at)}
                            </PropertyRow>
                        ) : null}
                        <PropertyRow label="Matching segments" className="px-3">
                            {membership.segments.length ? (
                                <span className="flex flex-wrap justify-end gap-1">
                                    {membership.segments.map((segment) => (
                                        <Badge
                                            key={segment.uuid}
                                            variant="secondary"
                                        >
                                            {segment.name}
                                        </Badge>
                                    ))}
                                </span>
                            ) : (
                                'Not in any segment'
                            )}
                        </PropertyRow>
                    </PropertyList>
                </NestedPanel>

                {membership.attributes.length ? (
                    <>
                        <Separator />
                        <NestedPanel
                            title="Custom attributes"
                            icon={
                                <HugeiconsIcon icon={InformationCircleIcon} />
                            }
                        >
                            <PropertyList>
                                {membership.attributes.map((attribute) => (
                                    <PropertyRow
                                        key={attribute.uuid}
                                        label={attribute.name}
                                        className="px-3"
                                    >
                                        {formatAttributeValue(attribute) || '—'}
                                    </PropertyRow>
                                ))}
                            </PropertyList>
                        </NestedPanel>
                    </>
                ) : null}

                <Separator />
                <NestedPanel title="Consent">
                    <PropertyList>
                        <PropertyRow
                            label="Recorded"
                            title={absoluteTime(membership.consented_at)}
                            className="px-3"
                        >
                            {membership.consented_at
                                ? formatRelativeTime(membership.consented_at)
                                : '—'}
                        </PropertyRow>
                        <PropertyRow label="IP address" className="px-3">
                            {membership.consent_ip || '—'}
                        </PropertyRow>
                        <div className="flex flex-col gap-1 px-3 py-3">
                            <p className="text-muted-foreground">
                                Consent text
                            </p>
                            <p>{membership.consent_text || '—'}</p>
                        </div>
                    </PropertyList>
                </NestedPanel>
            </CollapsibleContent>
        </Collapsible>
    );
}

function PropertyList({ children }: { children: ReactNode }) {
    return <div className="divide-y">{children}</div>;
}

function PropertyRow({
    label,
    children,
    href,
    title,
    className,
}: {
    label: ReactNode;
    children: ReactNode;
    href?: string;
    title?: string;
    className?: string;
}) {
    const content = (
        <>
            <span className="min-w-0 text-muted-foreground">{label}</span>
            <span className="min-w-0 text-right font-medium" title={title}>
                {children}
            </span>
        </>
    );

    if (href) {
        return (
            <a
                href={href}
                className={`flex items-center justify-between gap-4 px-(--card-spacing) py-3 text-sm transition-colors hover:bg-muted/50 ${className ?? ''}`}
            >
                {content}
            </a>
        );
    }

    return (
        <div
            className={`flex items-center justify-between gap-4 px-(--card-spacing) py-3 text-sm ${className ?? ''}`}
        >
            {content}
        </div>
    );
}

function IconLabel({
    icon,
    children,
}: {
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <span className="flex items-center gap-2 text-muted-foreground [&>svg]:size-3.5">
            {icon}
            {children}
        </span>
    );
}

function NestedPanel({
    title,
    icon,
    children,
}: {
    title: string;
    icon?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 bg-muted/30 px-(--card-spacing) py-4">
            <div className="flex items-center gap-2 text-sm font-medium">
                {icon ? (
                    <span className="text-muted-foreground [&>svg]:size-3.5">
                        {icon}
                    </span>
                ) : null}
                {title}
            </div>
            <div className="overflow-hidden rounded-md border bg-background">
                {children}
            </div>
        </div>
    );
}

function formatAttributeValue(attribute: ContactAttribute): string {
    if (attribute.value === null) {
        return '';
    }

    if (attribute.type === 'date') {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
        }).format(new Date(attribute.value));
    }

    if (attribute.type === 'boolean') {
        return attribute.value ? 'Yes' : 'No';
    }

    return String(attribute.value);
}

function absoluteTime(value: string | null): string | undefined {
    return value ? new Date(value).toLocaleString() : undefined;
}

function membershipActivityDetail(activity: ContactActivity): string {
    if (activity.audiences === 0) {
        return 'Not subscribed to an audience';
    }

    return `${activity.subscribed.toLocaleString()} subscribed across ${activity.audiences.toLocaleString()} audience${activity.audiences === 1 ? '' : 's'}`;
}
