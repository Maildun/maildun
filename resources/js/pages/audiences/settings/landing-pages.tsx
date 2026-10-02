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

export default function AudienceLandingPageSettings({ audience }: Props) {
    const { currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`Landing pages · ${audience.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="Landing pages"
                    description="Send people to your own page after they unsubscribe."
                />

                <Form
                    {...update.form(routeArgs)}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                >
                    {({ errors, processing }) => (
                        <SettingsPanel
                            variant="inset"
                            title="Redirects"
                            description="Paste a link for now. Custom pages will come later."
                        >
                            <FieldGroup className="p-6 sm:p-7">
                                <Field
                                    data-invalid={Boolean(
                                        errors.unsubscribed_url,
                                    )}
                                >
                                    <FieldLabel htmlFor="unsubscribed_url">
                                        When someone unsubscribes
                                    </FieldLabel>
                                    <Input
                                        id="unsubscribed_url"
                                        name="unsubscribed_url"
                                        type="url"
                                        data-test="audience-unsubscribed-url"
                                        placeholder="https://example.com/unsubscribed"
                                        defaultValue={
                                            audience.unsubscribed_url ?? ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.unsubscribed_url,
                                        )}
                                    />
                                    <FieldDescription>
                                        Paste a link shown after someone opts
                                        out.
                                    </FieldDescription>
                                    <FieldError>
                                        {errors.unsubscribed_url}
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
