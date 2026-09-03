import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import AudienceSettingsLayout from '@/layouts/audiences/settings-layout';
import { update } from '@/routes/audiences';
import type { Audience } from '@/types/audiences';

type Props = {
    audience: Audience;
};

export default function AudienceNotificationSettings({ audience }: Props) {
    const { currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`Email notification · ${audience.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="Email notification"
                    description="Get an email when someone subscribes or unsubscribes from this audience."
                />

                <Form
                    {...update.form(routeArgs)}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                >
                    {({ errors, processing }) => (
                        <SettingsPanel
                            variant="inset"
                            title="Alerts"
                            description="Optional. Leave blank to skip subscribe and unsubscribe emails."
                        >
                            <FieldGroup className="p-6 sm:p-7">
                                <Field
                                    data-invalid={Boolean(
                                        errors.notification_email,
                                    )}
                                >
                                    <FieldLabel htmlFor="notification_email">
                                        Notification email
                                    </FieldLabel>
                                    <Input
                                        id="notification_email"
                                        name="notification_email"
                                        type="email"
                                        data-test="audience-notification-email"
                                        maxLength={255}
                                        placeholder="you@example.com"
                                        defaultValue={
                                            audience.notification_email ?? ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.notification_email,
                                        )}
                                    />
                                    <FieldDescription>
                                        Subscribe and unsubscribe alerts will
                                        use this address.
                                    </FieldDescription>
                                    <FieldError>
                                        {errors.notification_email}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                            <div className="flex justify-end border-t px-6 py-5 sm:px-7">
                                <Button
                                    type="submit"
                                    data-test="save-audience-settings"
                                    disabled={processing}
                                >
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Save changes
                                </Button>
                            </div>
                        </SettingsPanel>
                    )}
                </Form>
            </div>
        </AudienceSettingsLayout>
    );
}
