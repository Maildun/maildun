import { Add01Icon, ArrowLeft01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link, useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { TagsCombobox } from '@/components/tags-combobox';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { lookup, show, store, update } from '@/routes/contacts';
import type { Tag } from '@/types/audiences';
import type {
    CompanyOption,
    ContactLookupContact,
    ContactLookupResponse,
    ContactSummary,
} from '@/types/contacts';

type ContactFormData = {
    email: string;
    first_name: string;
    last_name: string;
    company_assignment_mode: 'automatic' | 'manual';
    company_uuid: string;
    tags: string[];
    audience_uuids: string[];
    consent_confirmed: boolean;
};

type ContactDialogProps = {
    teamSlug: string;
    companies: CompanyOption[];
    tags: Tag[];
    audiences?: CompanyOption[];
    defaultAudience?: CompanyOption;
    contact?: ContactSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function ContactDialog({
    teamSlug,
    companies,
    tags,
    audiences = [],
    defaultAudience,
    contact,
    open,
    onOpenChange,
}: ContactDialogProps) {
    const [step, setStep] = useState<'email' | 'details'>(
        contact ? 'details' : 'email',
    );
    const [resolvedContact, setResolvedContact] =
        useState<ContactLookupContact | null>(null);
    const lookupRequest = useHttp<Record<string, never>, ContactLookupResponse>(
        {},
    );
    const form = useForm<ContactFormData>({
        email: contact?.email ?? '',
        first_name: contact?.first_name ?? '',
        last_name: contact?.last_name ?? '',
        company_assignment_mode:
            contact?.company_assignment_mode ?? 'automatic',
        company_uuid: contact?.company?.uuid ?? '',
        tags: contact?.tags.map((tag) => tag.name) ?? [],
        audience_uuids: [],
        consent_confirmed: false,
    });

    const subscribedAudienceUuids = new Set(
        resolvedContact?.memberships
            .filter((membership) => membership.status === 'subscribed')
            .map((membership) => membership.audience.uuid) ?? [],
    );
    const availableAudiences = audiences.filter(
        (audience) => !subscribedAudienceUuids.has(audience.uuid),
    );
    const defaultMembership = resolvedContact?.memberships.find(
        (membership) => membership.audience.uuid === defaultAudience?.uuid,
    );
    const alreadyInDefaultAudience = defaultMembership?.status === 'subscribed';
    const isExistingContact = resolvedContact !== null;
    const needsAudienceSelection = !contact && !defaultAudience;
    const hasAudienceSelection = form.data.audience_uuids.length > 0;
    const cannotSubmitExistingContact =
        isExistingContact && !hasAudienceSelection;

    const resetCreateFlow = (): void => {
        lookupRequest.cancel();
        lookupRequest.resetAndClearErrors();
        setStep('email');
        setResolvedContact(null);
        form.reset();
        form.clearErrors();
    };

    const handleOpenChange = (nextOpen: boolean): void => {
        onOpenChange(nextOpen);

        if (!nextOpen && !contact) {
            resetCreateFlow();
        }
    };

    const continueWithEmail = (event: React.FormEvent): void => {
        event.preventDefault();
        const email = form.data.email.trim().toLowerCase();

        if (email === '') {
            form.setError('email', 'Enter an email address.');

            return;
        }

        form.clearErrors('email');
        form.setData('email', email);

        void lookupRequest
            .get(lookup.url(teamSlug, { query: { email } }), {
                onSuccess: (response) => {
                    const existingContact = response.contact;
                    const existingDefaultMembership =
                        existingContact?.memberships.find(
                            (membership) =>
                                membership.audience.uuid ===
                                defaultAudience?.uuid,
                        );
                    const audienceUuids =
                        defaultAudience &&
                        existingDefaultMembership?.status !== 'subscribed'
                            ? [defaultAudience.uuid]
                            : [];

                    setResolvedContact(existingContact);
                    form.setData((data) => ({
                        ...data,
                        email,
                        audience_uuids: audienceUuids,
                        consent_confirmed: false,
                    }));
                    setStep('details');
                },
            })
            .catch(() => undefined);
    };

    const toggleAudience = (audienceUuid: string, checked: boolean): void => {
        form.setData(
            'audience_uuids',
            checked
                ? [...form.data.audience_uuids, audienceUuid]
                : form.data.audience_uuids.filter(
                      (uuid) => uuid !== audienceUuid,
                  ),
        );
    };

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();

        const onSuccess = (): void => {
            handleOpenChange(false);
        };

        if (contact) {
            form.put(update.url([teamSlug, contact.uuid]), { onSuccess });

            return;
        }

        form.post(store.url(teamSlug), { onSuccess });
    };

    const submitLabel = (): string => {
        if (contact) {
            return 'Save changes';
        }

        if (alreadyInDefaultAudience) {
            return 'Already in audience';
        }

        if (isExistingContact) {
            return form.data.audience_uuids.length > 1
                ? `Add to ${form.data.audience_uuids.length} audiences`
                : 'Add to audience';
        }

        return hasAudienceSelection ? 'Create and add' : 'Create contact';
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            {!contact ? (
                <DialogTrigger render={<Button />}>
                    <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" />
                    Add contact
                </DialogTrigger>
            ) : null}
            <DialogContent className="max-h-[calc(100svh-2rem)] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {contact ? 'Edit contact' : 'Add contact'}
                    </DialogTitle>
                    <DialogDescription>
                        {contact
                            ? 'Contact details and tags are shared across every audience this person belongs to.'
                            : 'Start with an email. Existing contacts keep their shared profile when you add them to another audience.'}
                    </DialogDescription>
                </DialogHeader>

                {!contact && step === 'email' ? (
                    <form onSubmit={continueWithEmail}>
                        <FieldGroup>
                            <Field
                                data-invalid={Boolean(
                                    form.errors.email ||
                                    lookupRequest.errors.email,
                                )}
                            >
                                <FieldLabel htmlFor="contact-email-new">
                                    Email
                                </FieldLabel>
                                <Input
                                    id="contact-email-new"
                                    type="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder="person@company.com"
                                    value={form.data.email}
                                    onChange={(event) => {
                                        lookupRequest.clearErrors();
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        );
                                    }}
                                    aria-invalid={Boolean(
                                        form.errors.email ||
                                        lookupRequest.errors.email,
                                    )}
                                />
                                <FieldDescription>
                                    We will check whether this person is already
                                    in your team.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.email ||
                                        lookupRequest.errors.email}
                                </FieldError>
                            </Field>
                        </FieldGroup>
                        <DialogFooter className="mt-6">
                            <Button
                                type="submit"
                                disabled={lookupRequest.processing}
                            >
                                {lookupRequest.processing ? (
                                    <Spinner data-icon="inline-start" />
                                ) : null}
                                Continue
                            </Button>
                        </DialogFooter>
                    </form>
                ) : (
                    <form onSubmit={submit}>
                        <FieldGroup>
                            {!contact ? (
                                <div className="flex items-center justify-between gap-3 rounded-lg border bg-muted/30 p-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {form.data.email}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {isExistingContact
                                                ? 'Existing team contact'
                                                : 'New team contact'}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={resetCreateFlow}
                                    >
                                        <HugeiconsIcon
                                            icon={ArrowLeft01Icon}
                                            data-icon="inline-start"
                                        />
                                        Use another email
                                    </Button>
                                </div>
                            ) : null}

                            {resolvedContact ? (
                                <div className="flex flex-col gap-4 rounded-lg border p-4">
                                    <div className="flex items-center gap-3">
                                        <Avatar size="lg">
                                            <AvatarImage
                                                src={resolvedContact.avatar}
                                                alt=""
                                            />
                                            <AvatarFallback>
                                                {(
                                                    resolvedContact
                                                        .first_name?.[0] ??
                                                    resolvedContact.email[0]
                                                ).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {[
                                                    resolvedContact.first_name,
                                                    resolvedContact.last_name,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ') ||
                                                    resolvedContact.email}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {resolvedContact.email}
                                            </p>
                                        </div>
                                        <Badge variant="success">
                                            Existing contact
                                        </Badge>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {resolvedContact.company ? (
                                            <Badge variant="outline">
                                                {resolvedContact.company.name}
                                            </Badge>
                                        ) : null}
                                        {resolvedContact.tags.map((tag) => (
                                            <Badge key={tag.uuid}>
                                                {tag.name}
                                            </Badge>
                                        ))}
                                    </div>
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <p className="text-muted-foreground">
                                            Shared name, company, and tags will
                                            not be changed.
                                        </p>
                                        <Button
                                            type="button"
                                            variant="link"
                                            size="sm"
                                            nativeButton={false}
                                            render={
                                                <Link
                                                    href={show.url([
                                                        teamSlug,
                                                        resolvedContact.uuid,
                                                    ])}
                                                    prefetch
                                                />
                                            }
                                        >
                                            View profile
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <NewContactFields
                                    form={form}
                                    companies={companies}
                                    tags={tags}
                                    contactUuid={contact?.uuid}
                                />
                            )}

                            {!contact && defaultAudience ? (
                                <Field>
                                    <FieldLabel>Audience</FieldLabel>
                                    <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                        <span className="text-sm font-medium">
                                            {defaultAudience.name}
                                        </span>
                                        <Badge
                                            variant={
                                                alreadyInDefaultAudience
                                                    ? 'success'
                                                    : defaultMembership
                                                      ? 'secondary'
                                                      : 'outline'
                                            }
                                        >
                                            {alreadyInDefaultAudience
                                                ? 'Already subscribed'
                                                : defaultMembership
                                                  ? 'Will resubscribe'
                                                  : 'Will be added'}
                                        </Badge>
                                    </div>
                                    {alreadyInDefaultAudience ? (
                                        <FieldDescription>
                                            This contact already belongs to this
                                            audience. No changes are needed.
                                        </FieldDescription>
                                    ) : null}
                                </Field>
                            ) : null}

                            {needsAudienceSelection ? (
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.audience_uuids,
                                    )}
                                >
                                    <FieldLabel>Audiences</FieldLabel>
                                    <FieldDescription>
                                        {isExistingContact
                                            ? 'Choose where to add this contact. Current subscriptions are hidden.'
                                            : 'Optional. You can create the contact without subscribing them.'}
                                    </FieldDescription>
                                    {availableAudiences.length > 0 ? (
                                        <div className="flex max-h-48 flex-col overflow-y-auto rounded-lg border">
                                            {availableAudiences.map(
                                                (audience) => {
                                                    const checkboxId = `contact-audience-${audience.uuid}`;
                                                    const membership =
                                                        resolvedContact?.memberships.find(
                                                            (item) =>
                                                                item.audience
                                                                    .uuid ===
                                                                audience.uuid,
                                                        );

                                                    return (
                                                        <label
                                                            key={audience.uuid}
                                                            htmlFor={checkboxId}
                                                            className="flex cursor-pointer items-center justify-between gap-3 border-b px-3 py-2.5 last:border-b-0"
                                                        >
                                                            <span className="flex items-center gap-3 text-sm">
                                                                <Checkbox
                                                                    id={
                                                                        checkboxId
                                                                    }
                                                                    checked={form.data.audience_uuids.includes(
                                                                        audience.uuid,
                                                                    )}
                                                                    onCheckedChange={(
                                                                        checked,
                                                                    ) =>
                                                                        toggleAudience(
                                                                            audience.uuid,
                                                                            checked ===
                                                                                true,
                                                                        )
                                                                    }
                                                                />
                                                                {audience.name}
                                                            </span>
                                                            {membership?.status ===
                                                            'unsubscribed' ? (
                                                                <Badge variant="secondary">
                                                                    Resubscribe
                                                                </Badge>
                                                            ) : null}
                                                        </label>
                                                    );
                                                },
                                            )}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                            {isExistingContact
                                                ? 'This contact is already subscribed to every audience.'
                                                : 'Create an audience first if you want to subscribe this contact.'}
                                        </div>
                                    )}
                                    <FieldError>
                                        {form.errors.audience_uuids}
                                    </FieldError>
                                </Field>
                            ) : null}

                            {!contact && hasAudienceSelection ? (
                                <Field
                                    orientation="horizontal"
                                    data-invalid={Boolean(
                                        form.errors.consent_confirmed,
                                    )}
                                >
                                    <Checkbox
                                        id="contact-consent-confirmed"
                                        checked={form.data.consent_confirmed}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'consent_confirmed',
                                                checked === true,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            form.errors.consent_confirmed,
                                        )}
                                    />
                                    <div className="flex flex-col gap-1">
                                        <FieldLabel htmlFor="contact-consent-confirmed">
                                            Marketing consent confirmed
                                        </FieldLabel>
                                        <FieldDescription>
                                            I confirm this person gave
                                            permission to receive marketing
                                            email.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.errors.consent_confirmed}
                                        </FieldError>
                                    </div>
                                </Field>
                            ) : null}
                        </FieldGroup>
                        <DialogFooter className="mt-6">
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    cannotSubmitExistingContact
                                }
                            >
                                {form.processing ? (
                                    <Spinner data-icon="inline-start" />
                                ) : null}
                                {submitLabel()}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function NewContactFields({
    form,
    companies,
    tags,
    contactUuid,
}: {
    form: ReturnType<typeof useForm<ContactFormData>>;
    companies: CompanyOption[];
    tags: Tag[];
    contactUuid?: string;
}) {
    const suffix = contactUuid ?? 'new';

    return (
        <>
            {contactUuid ? (
                <Field data-invalid={Boolean(form.errors.email)}>
                    <FieldLabel htmlFor={`contact-email-${suffix}`}>
                        Email
                    </FieldLabel>
                    <Input
                        id={`contact-email-${suffix}`}
                        type="email"
                        autoComplete="email"
                        placeholder="person@company.com"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.email)}
                    />
                    <FieldError>{form.errors.email}</FieldError>
                </Field>
            ) : null}
            <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={Boolean(form.errors.first_name)}>
                    <FieldLabel htmlFor={`contact-first-name-${suffix}`}>
                        First name
                    </FieldLabel>
                    <Input
                        id={`contact-first-name-${suffix}`}
                        autoComplete="given-name"
                        placeholder="Taylor"
                        value={form.data.first_name}
                        onChange={(event) =>
                            form.setData('first_name', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.first_name)}
                    />
                    <FieldError>{form.errors.first_name}</FieldError>
                </Field>
                <Field data-invalid={Boolean(form.errors.last_name)}>
                    <FieldLabel htmlFor={`contact-last-name-${suffix}`}>
                        Last name
                    </FieldLabel>
                    <Input
                        id={`contact-last-name-${suffix}`}
                        autoComplete="family-name"
                        placeholder="Otwell"
                        value={form.data.last_name}
                        onChange={(event) =>
                            form.setData('last_name', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.last_name)}
                    />
                    <FieldError>{form.errors.last_name}</FieldError>
                </Field>
            </div>
            <Field data-invalid={Boolean(form.errors.company_assignment_mode)}>
                <FieldLabel htmlFor={`contact-company-mode-${suffix}`}>
                    Company association
                </FieldLabel>
                <Select
                    value={form.data.company_assignment_mode}
                    onValueChange={(value) =>
                        form.setData(
                            'company_assignment_mode',
                            value as 'automatic' | 'manual',
                        )
                    }
                >
                    <SelectTrigger
                        id={`contact-company-mode-${suffix}`}
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="automatic">
                                Match the email domain automatically
                            </SelectItem>
                            <SelectItem value="manual">
                                Choose manually
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <FieldDescription>
                    Personal email providers are never matched automatically.
                </FieldDescription>
                <FieldError>{form.errors.company_assignment_mode}</FieldError>
            </Field>
            {form.data.company_assignment_mode === 'manual' ? (
                <Field data-invalid={Boolean(form.errors.company_uuid)}>
                    <FieldLabel htmlFor={`contact-company-${suffix}`}>
                        Company
                    </FieldLabel>
                    <Select
                        value={form.data.company_uuid || null}
                        onValueChange={(value) =>
                            form.setData(
                                'company_uuid',
                                (value as string | null) ?? '',
                            )
                        }
                    >
                        <SelectTrigger
                            id={`contact-company-${suffix}`}
                            className="w-full"
                        >
                            <SelectValue placeholder="No company" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value={null}>No company</SelectItem>
                                {companies.map((company) => (
                                    <SelectItem
                                        key={company.uuid}
                                        value={company.uuid}
                                    >
                                        {company.name}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <FieldError>{form.errors.company_uuid}</FieldError>
                </Field>
            ) : null}
            <Field data-invalid={Boolean(form.errors.tags)}>
                <FieldLabel>Tags</FieldLabel>
                <TagsCombobox
                    value={form.data.tags}
                    availableTags={tags}
                    onValueChange={(value) => form.setData('tags', value)}
                    aria-invalid={Boolean(form.errors.tags)}
                />
                <FieldError>{form.errors.tags}</FieldError>
            </Field>
        </>
    );
}
