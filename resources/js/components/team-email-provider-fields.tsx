import PasswordInput from '@/components/password-input';
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

export type EmailProviderIntegration = {
    uuid: string;
    name: string;
    provider: string;
    provider_label: string;
    settings: Record<string, string | number | null>;
    has_secret: boolean;
    connected_at: string | null;
    last_tested_at: string | null;
    test_from_address: string | null;
    trust_provider_senders: boolean;
    delivery_is_verified: boolean;
    verified_sender_count: number;
    configuration_is_complete: boolean;
    /** Amazon SES only: when feedback last arrived and whether it has gone quiet. */
    feedback: {
        last_feedback_at: string | null;
        last_sent_at: string | null;
        stale: boolean;
    } | null;
};

export const SECRET_FIELD_NAMES = ['smtp_password', 'ses_secret_access_key'];

const SMTP_ENCRYPTION_OPTIONS = [
    { value: 'tls', label: 'TLS (recommended)' },
    { value: 'ssl', label: 'SSL' },
    { value: 'none', label: 'None' },
];

const SMTP_PORT_OPTIONS = [25, 465, 587, 2525].map((port) => ({
    value: String(port),
    label: String(port),
}));

type SecretFieldProps = {
    id: string;
    name: string;
    label: string;
    placeholder: string;
    error?: string;
    hasStoredSecret: boolean;
    required?: boolean;
    description: string;
};

function SecretField({
    id,
    name,
    label,
    placeholder,
    error,
    hasStoredSecret,
    required = true,
    description,
}: SecretFieldProps) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <PasswordInput
                id={id}
                name={name}
                autoComplete="new-password"
                placeholder={
                    hasStoredSecret
                        ? 'Stored credential — leave blank to keep it'
                        : placeholder
                }
                required={required && !hasStoredSecret}
                aria-invalid={Boolean(error)}
                data-test={name}
            />
            <FieldDescription>
                {hasStoredSecret
                    ? 'A credential is stored securely. Enter a new value only to replace it.'
                    : description}
            </FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

type ProviderFieldsProps = {
    provider: string;
    integration: EmailProviderIntegration | null;
    errors: Record<string, string>;
    webhookUrl: string;
};

export function TeamEmailProviderFields({
    provider,
    integration,
    errors,
    webhookUrl,
}: ProviderFieldsProps) {
    const settings = integration?.settings ?? {};
    const hasStoredSecret = integration?.has_secret ?? false;
    const setting = (
        key: string,
        fallback: string | number = '',
    ): string | number => {
        const value = settings[key];

        return typeof value === 'string' || typeof value === 'number'
            ? value
            : fallback;
    };

    if (provider === 'smtp') {
        return (
            <FieldGroup className="grid gap-5 sm:grid-cols-2">
                <Field data-invalid={Boolean(errors.smtp_host)}>
                    <FieldLabel htmlFor="smtp_host">SMTP host</FieldLabel>
                    <Input
                        id="smtp_host"
                        name="smtp_host"
                        defaultValue={setting('host')}
                        placeholder="smtp.example.com"
                        required
                        aria-invalid={Boolean(errors.smtp_host)}
                        data-test="smtp-host"
                    />
                    <FieldError>{errors.smtp_host}</FieldError>
                </Field>
                <Field data-invalid={Boolean(errors.smtp_port)}>
                    <FieldLabel htmlFor="smtp_port">Port</FieldLabel>
                    <Select
                        name="smtp_port"
                        items={SMTP_PORT_OPTIONS}
                        defaultValue={String(setting('port', 587))}
                        required
                    >
                        <SelectTrigger
                            id="smtp_port"
                            aria-invalid={Boolean(errors.smtp_port)}
                            data-test="smtp-port"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {SMTP_PORT_OPTIONS.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <FieldDescription>
                        Supported ports: 25, 465, 587, and 2525.
                    </FieldDescription>
                    <FieldError>{errors.smtp_port}</FieldError>
                </Field>
                <Field data-invalid={Boolean(errors.smtp_username)}>
                    <FieldLabel htmlFor="smtp_username">Username</FieldLabel>
                    <Input
                        id="smtp_username"
                        name="smtp_username"
                        defaultValue={setting('username')}
                        autoComplete="username"
                        placeholder="mailer@example.com"
                        aria-invalid={Boolean(errors.smtp_username)}
                        data-test="smtp-username"
                    />
                    <FieldError>{errors.smtp_username}</FieldError>
                </Field>
                <SecretField
                    id="smtp_password"
                    name="smtp_password"
                    label="Password"
                    placeholder="Enter the SMTP password"
                    error={errors.smtp_password}
                    hasStoredSecret={hasStoredSecret}
                    required={false}
                    description="Optional for SMTP servers that do not require authentication."
                />
                <Field
                    data-invalid={Boolean(errors.smtp_encryption)}
                    className="sm:col-span-2"
                >
                    <FieldLabel htmlFor="smtp_encryption">
                        Encryption
                    </FieldLabel>
                    <Select
                        name="smtp_encryption"
                        items={SMTP_ENCRYPTION_OPTIONS}
                        defaultValue={String(setting('encryption', 'tls'))}
                        required
                    >
                        <SelectTrigger
                            id="smtp_encryption"
                            aria-invalid={Boolean(errors.smtp_encryption)}
                            data-test="smtp-encryption"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                {SMTP_ENCRYPTION_OPTIONS.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <FieldError>{errors.smtp_encryption}</FieldError>
                </Field>
            </FieldGroup>
        );
    }

    if (provider === 'ses') {
        return (
            <FieldGroup className="grid gap-5 sm:grid-cols-2">
                <Field
                    data-invalid={Boolean(errors.ses_region)}
                    className="sm:col-span-2"
                >
                    <FieldLabel htmlFor="ses_region">AWS region</FieldLabel>
                    <Input
                        id="ses_region"
                        name="ses_region"
                        defaultValue={setting('region', 'us-east-1')}
                        placeholder="us-east-1"
                        required
                        aria-invalid={Boolean(errors.ses_region)}
                        data-test="ses-region"
                    />
                    <FieldError>{errors.ses_region}</FieldError>
                </Field>
                <Field data-invalid={Boolean(errors.ses_access_key_id)}>
                    <FieldLabel htmlFor="ses_access_key_id">
                        Access key ID
                    </FieldLabel>
                    <Input
                        id="ses_access_key_id"
                        name="ses_access_key_id"
                        defaultValue={setting('access_key_id')}
                        autoComplete="off"
                        placeholder="AKIAIOSFODNN7EXAMPLE"
                        required
                        aria-invalid={Boolean(errors.ses_access_key_id)}
                        data-test="ses-access-key-id"
                    />
                    <FieldError>{errors.ses_access_key_id}</FieldError>
                </Field>
                <SecretField
                    id="ses_secret_access_key"
                    name="ses_secret_access_key"
                    label="Secret access key"
                    placeholder="Enter the IAM secret access key"
                    error={errors.ses_secret_access_key}
                    hasStoredSecret={hasStoredSecret}
                    description="Use an IAM user with ses:SendEmail and ses:GetConfigurationSetEventDestinations. These are not SES SMTP credentials."
                />
                <Field
                    data-invalid={Boolean(errors.ses_configuration_set)}
                    className="sm:col-span-2"
                >
                    <FieldLabel htmlFor="ses_configuration_set">
                        Configuration set
                    </FieldLabel>
                    <Input
                        id="ses_configuration_set"
                        name="ses_configuration_set"
                        defaultValue={setting('configuration_set')}
                        placeholder="maildun-feedback"
                        required
                        maxLength={64}
                        aria-invalid={Boolean(errors.ses_configuration_set)}
                        data-test="ses-configuration-set"
                    />
                    <FieldDescription>
                        This configuration set must publish SES feedback to the
                        SNS topic below.
                    </FieldDescription>
                    <FieldError>{errors.ses_configuration_set}</FieldError>
                </Field>
                <Field
                    data-invalid={Boolean(errors.ses_sns_topic_arn)}
                    className="sm:col-span-2"
                >
                    <FieldLabel htmlFor="ses_sns_topic_arn">
                        SNS topic ARN
                    </FieldLabel>
                    <Input
                        id="ses_sns_topic_arn"
                        name="ses_sns_topic_arn"
                        defaultValue={setting('sns_topic_arn')}
                        placeholder="arn:aws:sns:us-east-1:123456789012:maildun-feedback"
                        required
                        aria-invalid={Boolean(errors.ses_sns_topic_arn)}
                        data-test="ses-sns-topic-arn"
                    />
                    <FieldDescription>
                        Use an SNS topic in the same AWS region as this SES
                        connection.
                    </FieldDescription>
                    <FieldError>{errors.ses_sns_topic_arn}</FieldError>
                </Field>
                <div className="flex flex-col gap-3 rounded-lg border bg-muted/40 p-4 text-sm sm:col-span-2">
                    <div className="flex flex-col gap-1">
                        <p className="font-medium">
                            Enable SES campaign feedback
                        </p>
                        <ol className="list-decimal space-y-1 pl-5 text-muted-foreground">
                            <li>
                                Add an Amazon SNS event destination to the
                                configuration set.
                            </li>
                            <li>
                                Enable Delivery, Bounce, and Complaint events
                                only. Maildun tracks campaign opens and clicks
                                itself.
                            </li>
                            <li>
                                Subscribe the SNS topic to the HTTPS endpoint
                                below. Maildun confirms the subscription
                                automatically.
                            </li>
                        </ol>
                    </div>
                    <Field>
                        <FieldLabel htmlFor="ses_webhook_url">
                            SNS webhook URL
                        </FieldLabel>
                        <Input
                            id="ses_webhook_url"
                            value={webhookUrl}
                            placeholder="https://example.com/webhooks/aws/ses"
                            readOnly
                            data-test="ses-webhook-url"
                        />
                    </Field>
                </div>
            </FieldGroup>
        );
    }

    return null;
}
