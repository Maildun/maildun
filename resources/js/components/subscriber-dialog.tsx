import { Add01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useForm } from '@inertiajs/react';
import { TagsCombobox } from '@/components/tags-combobox';
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
import { tagsError } from '@/lib/tags';
import {
    store as storeSubscriber,
    update as updateSubscriber,
} from '@/routes/audiences/subscribers';
import type {
    Subscriber,
    SubscriberLanguage,
    SubscriberLanguageOption,
    Tag,
} from '@/types/audiences';

export function SubscriberDialog({
    open,
    onOpenChange,
    routeArgs,
    subscriber,
    canEditProfile = true,
    tags,
    languages = [],
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    routeArgs: [string, string];
    subscriber?: Subscriber;
    canEditProfile?: boolean;
    tags: Tag[];
    languages?: SubscriberLanguageOption[];
}) {
    const form = useForm({
        email: subscriber?.email ?? '',
        first_name: subscriber?.first_name ?? '',
        last_name: subscriber?.last_name ?? '',
        language: (subscriber?.language ?? null) as SubscriberLanguage | null,
        tags: subscriber?.tags.map((tag) => tag.name) ?? [],
        consent_confirmed: false,
    });
    const languageOptions =
        languages.length > 0 &&
        subscriber?.language &&
        !languages.some((option) => option.value === subscriber.language)
            ? [
                  ...languages,
                  {
                      value: subscriber.language,
                      label: subscriber.language_name ?? subscriber.language,
                  },
              ]
            : languages;

    const profileReadOnly = Boolean(subscriber) && !canEditProfile;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform(({ language, ...data }) =>
            languageOptions.length > 0 ? { ...data, language } : data,
        );

        const onSuccess = () => {
            onOpenChange(false);

            if (!subscriber) {
                form.reset();
            }
        };

        if (subscriber) {
            form.put(
                updateSubscriber.url([
                    routeArgs[0],
                    routeArgs[1],
                    subscriber.uuid,
                ]),
                { onSuccess },
            );
        } else {
            form.post(storeSubscriber.url(routeArgs), { onSuccess });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {!subscriber && (
                <DialogTrigger render={<Button />}>
                    <HugeiconsIcon icon={Add01Icon} data-icon="inline-start" />
                    Add contact
                </DialogTrigger>
            )}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {subscriber ? 'Edit contact' : 'Add contact'}
                    </DialogTitle>
                    <DialogDescription>
                        {subscriber
                            ? 'Update this contact’s details and tags across every audience.'
                            : 'Only add people who have agreed to receive marketing email.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field data-invalid={Boolean(form.errors.email)}>
                            <FieldLabel
                                htmlFor={`email-${subscriber?.uuid || 'new'}`}
                            >
                                Email
                            </FieldLabel>
                            <Input
                                id={`email-${subscriber?.uuid || 'new'}`}
                                type="email"
                                placeholder="email@example.com"
                                value={form.data.email}
                                readOnly={profileReadOnly}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.email)}
                            />
                            {profileReadOnly && (
                                <FieldDescription>
                                    Email and name are shared across every
                                    audience. Editing them needs the contact
                                    permission.
                                </FieldDescription>
                            )}
                            <FieldError>{form.errors.email}</FieldError>
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(form.errors.first_name)}
                            >
                                <FieldLabel
                                    htmlFor={`first-${subscriber?.uuid || 'new'}`}
                                >
                                    First name
                                </FieldLabel>
                                <Input
                                    id={`first-${subscriber?.uuid || 'new'}`}
                                    placeholder="Taylor"
                                    value={form.data.first_name}
                                    readOnly={profileReadOnly}
                                    onChange={(event) =>
                                        form.setData(
                                            'first_name',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.first_name,
                                    )}
                                />
                                <FieldError>
                                    {form.errors.first_name}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(form.errors.last_name)}
                            >
                                <FieldLabel
                                    htmlFor={`last-${subscriber?.uuid || 'new'}`}
                                >
                                    Last name
                                </FieldLabel>
                                <Input
                                    id={`last-${subscriber?.uuid || 'new'}`}
                                    placeholder="Otwell"
                                    value={form.data.last_name}
                                    readOnly={profileReadOnly}
                                    onChange={(event) =>
                                        form.setData(
                                            'last_name',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.last_name,
                                    )}
                                />
                                <FieldError>{form.errors.last_name}</FieldError>
                            </Field>
                        </div>
                        {languageOptions.length > 0 && (
                            <Field data-invalid={Boolean(form.errors.language)}>
                                <FieldLabel
                                    htmlFor={`language-${subscriber?.uuid || 'new'}`}
                                >
                                    Language
                                </FieldLabel>
                                <Select
                                    value={form.data.language}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'language',
                                            value as SubscriberLanguage | null,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id={`language-${subscriber?.uuid || 'new'}`}
                                        className="w-full"
                                        aria-invalid={Boolean(
                                            form.errors.language,
                                        )}
                                    >
                                        <SelectValue placeholder="No language" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value={null}>
                                                No language
                                            </SelectItem>
                                            {languageOptions.map((language) => (
                                                <SelectItem
                                                    key={language.value}
                                                    value={language.value}
                                                >
                                                    {language.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldError>{form.errors.language}</FieldError>
                            </Field>
                        )}
                        <Field data-invalid={Boolean(tagsError(form.errors))}>
                            <FieldLabel>Tags</FieldLabel>
                            <TagsCombobox
                                value={form.data.tags}
                                availableTags={tags}
                                onValueChange={(next) =>
                                    form.setData('tags', next)
                                }
                                aria-invalid={Boolean(tagsError(form.errors))}
                            />
                            <FieldError>{tagsError(form.errors)}</FieldError>
                        </Field>
                        {!subscriber && (
                            <Field
                                orientation="horizontal"
                                data-invalid={Boolean(
                                    form.errors.consent_confirmed,
                                )}
                            >
                                <Checkbox
                                    id="consent_confirmed"
                                    checked={form.data.consent_confirmed}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'consent_confirmed',
                                            checked === true,
                                        )
                                    }
                                />
                                <div className="flex flex-col gap-1">
                                    <FieldLabel htmlFor="consent_confirmed">
                                        Marketing consent confirmed
                                    </FieldLabel>
                                    <FieldDescription>
                                        I confirm this person gave permission to
                                        receive marketing email.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.errors.consent_confirmed}
                                    </FieldError>
                                </div>
                            </Field>
                        )}
                    </FieldGroup>
                    <DialogFooter className="mt-6">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            {subscriber ? 'Save changes' : 'Add contact'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
