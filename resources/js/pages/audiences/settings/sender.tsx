import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AudienceSettingsLayout from '@/layouts/audiences/settings-layout';
import { update } from '@/routes/audiences';
import { edit as editWorkspaceSenders } from '@/routes/teams/sender';
import type {
    Audience,
    AudienceSenderFallbacks,
    AudienceSenderOption,
} from '@/types/audiences';

const workspaceDefaultSender = 'workspace-default';

type Props = {
    audience: Audience;
    senderFallbacks: AudienceSenderFallbacks;
    senders: AudienceSenderOption[];
    selectedSenderUuid: string | null;
};

export default function AudienceSenderSettings({
    audience,
    senderFallbacks,
    senders,
    selectedSenderUuid,
}: Props) {
    const { currentTeam } = usePage().props;
    const [senderUuid, setSenderUuid] = useState<string | null>(
        selectedSenderUuid,
    );

    if (!currentTeam) {
        return null;
    }

    const routeArgs: [string, string] = [currentTeam.slug, audience.uuid];
    const defaultSender = senderFallbacks.from_address
        ? `${senderFallbacks.from_name ? `${senderFallbacks.from_name} — ` : ''}${senderFallbacks.from_address}`
        : 'Not configured';
    const senderItems = [
        {
            label: `Workspace default — ${defaultSender}`,
            value: workspaceDefaultSender,
        },
        ...senders.map((sender) => ({
            label: sender.name
                ? `${sender.name} — ${sender.email}`
                : sender.email,
            value: sender.uuid,
        })),
    ];

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`Sender · ${audience.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="Sender"
                    description="Choose the verified sender used for emails sent to this audience."
                />

                <Form
                    {...update.form(routeArgs)}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                >
                    {({ errors, processing }) => (
                        <SettingsPanel
                            variant="inset"
                            title="From"
                            description="Select a workspace sender instead of entering sender details manually."
                        >
                            <FieldGroup className="p-6 sm:p-7">
                                <Field
                                    data-invalid={Boolean(errors.sender_uuid)}
                                >
                                    <FieldLabel htmlFor="sender_uuid">
                                        Sender
                                    </FieldLabel>
                                    <Select
                                        name="sender_uuid"
                                        items={senderItems}
                                        value={senderUuid}
                                        onValueChange={(value) =>
                                            setSenderUuid(
                                                typeof value === 'string'
                                                    ? value
                                                    : null,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="sender_uuid"
                                            className="w-full sm:max-w-xl"
                                            data-test="audience-sender-select"
                                            aria-invalid={Boolean(
                                                errors.sender_uuid,
                                            )}
                                        >
                                            <SelectValue placeholder="Select a sender" />
                                        </SelectTrigger>
                                        <SelectContent
                                            alignItemWithTrigger={false}
                                        >
                                            <SelectGroup>
                                                {senderItems.map((sender) => (
                                                    <SelectItem
                                                        key={sender.value}
                                                        value={sender.value}
                                                    >
                                                        {sender.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldDescription>
                                        Only verified workspace senders are
                                        available.{' '}
                                        <Link
                                            href={editWorkspaceSenders(
                                                currentTeam.slug,
                                            )}
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            Manage senders
                                        </Link>
                                        .
                                    </FieldDescription>
                                    <FieldError>
                                        {errors.sender_uuid}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                            <div className="flex justify-end border-t px-6 py-5 sm:px-7">
                                <Button
                                    type="submit"
                                    data-test="save-audience-settings"
                                    disabled={processing || senderUuid === null}
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
