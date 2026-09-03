import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import AudienceSettingsLayout from '@/layouts/audiences/settings-layout';
import { update } from '@/routes/audiences';
import { index as transactionalEmailsIndex } from '@/routes/transactional_emails';
import type { Audience } from '@/types/audiences';

type TransactionalEmailOption = {
    uuid: string;
    name: string;
    subject: string;
    published: boolean;
};

type Props = {
    audience: Audience;
    transactionalEmails: TransactionalEmailOption[];
};

export default function AudienceDoubleOptInSettings({
    audience,
    transactionalEmails,
}: Props) {
    const { currentTeam } = usePage().props;
    const form = useForm({
        double_opt_in: audience.double_opt_in,
        double_opt_in_email_uuid: audience.double_opt_in_email_uuid ?? '',
    });

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];
    const selectedEmail = transactionalEmails.find(
        (email) => email.uuid === form.data.double_opt_in_email_uuid,
    );
    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(update.url(routeArgs), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`Double opt-in · ${audience.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="Double opt-in"
                    description="Ask subscribers to confirm their email address before they join this audience."
                />

                <form onSubmit={save}>
                    <SettingsPanel
                        variant="inset"
                        title="Email confirmation"
                        description="Use a published transactional email to send the confirmation request."
                    >
                        <FieldGroup className="p-6 sm:p-7">
                            <Field orientation="horizontal">
                                <FieldContent>
                                    <FieldLabel htmlFor="double_opt_in">
                                        Require email confirmation
                                    </FieldLabel>
                                    <FieldDescription>
                                        New subscribers remain unconfirmed until
                                        they use the link in the email.
                                    </FieldDescription>
                                </FieldContent>
                                <Switch
                                    id="double_opt_in"
                                    name="double_opt_in"
                                    checked={form.data.double_opt_in}
                                    onCheckedChange={(checked) =>
                                        form.setData('double_opt_in', checked)
                                    }
                                    data-test="audience-double-opt-in"
                                    aria-invalid={Boolean(
                                        form.errors.double_opt_in,
                                    )}
                                />
                            </Field>

                            <Field
                                data-invalid={Boolean(
                                    form.errors.double_opt_in_email_uuid,
                                )}
                            >
                                <FieldLabel htmlFor="double_opt_in_email_uuid">
                                    Confirmation email
                                </FieldLabel>
                                <Select
                                    name="double_opt_in_email_uuid"
                                    value={
                                        form.data.double_opt_in_email_uuid ||
                                        null
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'double_opt_in_email_uuid',
                                            typeof value === 'string'
                                                ? value
                                                : '',
                                        )
                                    }
                                    disabled={
                                        !form.data.double_opt_in ||
                                        transactionalEmails.length === 0
                                    }
                                >
                                    <SelectTrigger
                                        id="double_opt_in_email_uuid"
                                        className="w-full sm:max-w-md"
                                        data-test="audience-double-opt-in-email"
                                        aria-invalid={Boolean(
                                            form.errors
                                                .double_opt_in_email_uuid,
                                        )}
                                    >
                                        <SelectValue placeholder="Select a transactional email" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {transactionalEmails.map(
                                                (email) => (
                                                    <SelectItem
                                                        key={email.uuid}
                                                        value={email.uuid}
                                                        disabled={
                                                            !email.published
                                                        }
                                                    >
                                                        {email.name}
                                                        {!email.published &&
                                                            ' (publish first)'}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                {transactionalEmails.length === 0 ? (
                                    <FieldDescription>
                                        Create and publish a transactional email
                                        before enabling double opt-in.{' '}
                                        <Link
                                            href={transactionalEmailsIndex(
                                                currentTeam.slug,
                                            )}
                                            className="font-medium"
                                        >
                                            Manage transactional emails
                                        </Link>
                                        .
                                    </FieldDescription>
                                ) : selectedEmail ? (
                                    <FieldDescription>
                                        Add{' '}
                                        <code>{'{{ confirmation_url }}'}</code>{' '}
                                        to “{selectedEmail.name}” so each
                                        subscriber receives their own
                                        confirmation link.
                                    </FieldDescription>
                                ) : null}
                                <FieldError>
                                    {form.errors.double_opt_in_email_uuid}
                                </FieldError>
                            </Field>
                        </FieldGroup>
                        <div className="flex justify-end border-t px-6 py-5 sm:px-7">
                            <Button
                                type="submit"
                                data-test="save-audience-settings"
                                disabled={form.processing}
                            >
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Save changes
                            </Button>
                        </div>
                    </SettingsPanel>
                </form>
            </div>
        </AudienceSettingsLayout>
    );
}
