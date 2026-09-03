import {
    FilterIcon,
    Mail01Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AudienceSettingsLayout from '@/layouts/audiences/settings-layout';
import { formatRelativeTime } from '@/lib/format';
import { show, update } from '@/routes/audiences';
import type { Audience } from '@/types/audiences';

type AudienceStats = {
    subscribers: number;
    subscribed: number;
    segments: number;
    forms: number;
    created_at: string;
};

type Props = {
    audience: Audience;
    stats: AudienceStats;
};

type RouteArgs = [string, string];

export default function AudienceEdit({ audience, stats }: Props) {
    const { currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const routeArgs: RouteArgs = [currentTeam.slug, audience.uuid];
    const audienceUrl = show.url(routeArgs);

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`${audience.name} settings`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="General"
                    description="Name this audience and keep a short description for your team."
                />

                <Form
                    {...update.form(routeArgs)}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                >
                    {({ errors, processing }) => (
                        <SettingsPanel
                            variant="inset"
                            title="Details"
                            description="Shown on the audience list and at the top of this audience."
                        >
                            <FieldGroup className="p-6 sm:p-7">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="audience-name">
                                        Name
                                    </FieldLabel>
                                    <Input
                                        id="audience-name"
                                        name="name"
                                        defaultValue={audience.name}
                                        required
                                        placeholder="Product newsletter"
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(errors.description)}
                                >
                                    <FieldLabel htmlFor="audience-description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="audience-description"
                                        name="description"
                                        defaultValue={
                                            audience.description || ''
                                        }
                                        placeholder="What this audience is for"
                                        rows={4}
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                    />
                                    <FieldError>
                                        {errors.description}
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

                <SettingsPanel
                    variant="inset"
                    title="Overview"
                    description="Counts for this audience. Open a number to jump back to the audience."
                >
                    <div className="divide-y">
                        <OverviewRow
                            icon={UserGroupIcon}
                            label="Subscribers"
                            value={stats.subscribers}
                            href={audienceUrl}
                        />
                        <OverviewRow
                            icon={FilterIcon}
                            label="Segments"
                            value={stats.segments}
                            href={audienceUrl}
                        />
                        <OverviewRow
                            icon={Mail01Icon}
                            label="Subscribe forms"
                            value={stats.forms}
                            href={audienceUrl}
                        />
                    </div>
                    <p className="border-t px-6 py-5 text-sm text-muted-foreground sm:px-7">
                        Created {formatRelativeTime(stats.created_at)}
                    </p>
                </SettingsPanel>
            </div>
        </AudienceSettingsLayout>
    );
}

function OverviewRow({
    icon,
    label,
    value,
    href,
}: {
    icon: IconSvgElement;
    label: string;
    value: number;
    href: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4 px-6 py-5 sm:px-7">
            <div className="flex items-center gap-2 text-sm">
                <HugeiconsIcon icon={icon} className="size-4" />
                {label}
            </div>
            <Link
                href={href}
                prefetch
                className="text-sm font-medium underline underline-offset-2"
            >
                {value}
            </Link>
        </div>
    );
}
