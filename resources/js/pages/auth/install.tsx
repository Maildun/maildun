import { Alert02Icon, CheckmarkCircle02Icon } from '@hugeicons/core-free-icons';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import InstallationController from '@/actions/App/Http/Controllers/InstallationController';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Callout,
    CalloutContent,
    CalloutHeading,
    CalloutText,
} from '@/components/ui/callout';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type InstallationCheck = {
    key: string;
    label: string;
    description: string;
    ready: boolean;
};

type Props = {
    checks: InstallationCheck[];
    passwordRules: string;
    ready: boolean;
    signedQuery: {
        expires: string;
        signature: string;
    };
    systemTestUrl: string;
};

export default function Install({
    checks,
    passwordRules,
    ready,
    signedQuery,
    systemTestUrl,
}: Props) {
    const { registrationOpen } = usePage().props;

    return (
        <>
            <Head title="Install" />

            <div className="flex flex-col gap-6">
                <Card size="sm">
                    <CardHeader>
                        <CardTitle>Deployment checks</CardTitle>
                        <CardDescription>
                            Forge or Laravel Cloud provides these services
                            before account setup.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex flex-col gap-3">
                            {checks.map((check) => (
                                <li
                                    key={check.key}
                                    className="flex items-start justify-between gap-3"
                                >
                                    <div className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-sm font-medium">
                                            {check.label}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {check.description}
                                        </span>
                                    </div>
                                    <Badge
                                        variant={
                                            check.ready
                                                ? 'success'
                                                : 'destructive'
                                        }
                                    >
                                        {check.ready
                                            ? 'Ready'
                                            : 'Action needed'}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                        <Button
                            variant="outline"
                            className="mt-4 w-full"
                            render={<Link href={systemTestUrl} />}
                        >
                            Test the full system
                        </Button>
                    </CardContent>
                </Card>

                {!ready && (
                    <Callout variant="danger" icon={Alert02Icon} role="alert">
                        <CalloutContent>
                            <CalloutHeading>
                                Deployment is not ready
                            </CalloutHeading>
                            <CalloutText>
                                Run app:install again after correcting the
                                failed service in Forge or Laravel Cloud.
                            </CalloutText>
                        </CalloutContent>
                    </Callout>
                )}

                <Form
                    {...InstallationController.store.form({
                        query: signedQuery,
                    })}
                    resetOnSuccess={['password', 'password_confirmation']}
                    disableWhileProcessing
                >
                    {({ errors, processing }) => (
                        <FieldGroup>
                            {errors.installation && (
                                <Callout
                                    variant="danger"
                                    icon={Alert02Icon}
                                    role="alert"
                                >
                                    <CalloutContent>
                                        <CalloutHeading>
                                            Installation unavailable
                                        </CalloutHeading>
                                        <CalloutText>
                                            {errors.installation}
                                        </CalloutText>
                                    </CalloutContent>
                                </Callout>
                            )}

                            <Field data-invalid={Boolean(errors.name)}>
                                <FieldLabel htmlFor="name">
                                    Administrator name
                                </FieldLabel>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    placeholder="Full name"
                                    autoComplete="name"
                                    autoFocus
                                    required
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <FieldError>{errors.name}</FieldError>
                            </Field>

                            <Field data-invalid={Boolean(errors.email)}>
                                <FieldLabel htmlFor="email">
                                    Email address
                                </FieldLabel>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    placeholder="email@example.com"
                                    autoComplete="email"
                                    required
                                    aria-invalid={Boolean(errors.email)}
                                />
                                <FieldError>{errors.email}</FieldError>
                            </Field>

                            <Field data-invalid={Boolean(errors.password)}>
                                <FieldLabel htmlFor="password">
                                    Password
                                </FieldLabel>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    placeholder="Password"
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    required
                                    aria-invalid={Boolean(errors.password)}
                                />
                                <FieldError>{errors.password}</FieldError>
                            </Field>

                            <Field
                                data-invalid={Boolean(
                                    errors.password_confirmation,
                                )}
                            >
                                <FieldLabel htmlFor="password_confirmation">
                                    Confirm password
                                </FieldLabel>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    required
                                    aria-invalid={Boolean(
                                        errors.password_confirmation,
                                    )}
                                />
                                <FieldError>
                                    {errors.password_confirmation}
                                </FieldError>
                            </Field>

                            <Callout icon={CheckmarkCircle02Icon}>
                                <CalloutContent>
                                    <CalloutHeading>
                                        {registrationOpen
                                            ? 'Public registration is open'
                                            : 'Registration is invitation-only'}
                                    </CalloutHeading>
                                    <CalloutText>
                                        This follows REGISTRATION_ENABLED in the
                                        hosting environment and can be changed
                                        there without rebuilding Maildun.
                                    </CalloutText>
                                </CalloutContent>
                            </Callout>

                            <Button
                                type="submit"
                                disabled={!ready || processing}
                                className="w-full"
                            >
                                {processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Create administrator
                            </Button>
                        </FieldGroup>
                    )}
                </Form>
            </div>
        </>
    );
}

Install.layout = {
    title: 'Finish installing Maildun',
    description:
        'Create the first administrator. This secure setup link stops working after completion.',
};
