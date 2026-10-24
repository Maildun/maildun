import { Shield01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import ManageGoogleAccount from '@/components/manage-google-account';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes';

type Props = {
    passwordRules: string;
    hasPassword: boolean;
    canManageGoogle: boolean;
    googleConnected: boolean;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Password & security" />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="Password & security" />
                <SettingsPanel
                    variant="inset"
                    title={
                        props.hasPassword ? 'Change password' : 'Set password'
                    }
                    description={
                        props.hasPassword
                            ? 'Use a unique password you do not reuse on other sites.'
                            : 'You log in with Google. Add a password to also log in with your email address.'
                    }
                >
                    <Form
                        {...SecurityController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        resetOnSuccess
                        onSuccess={() =>
                            toast.add({
                                type: 'success',
                                title: 'Changes saved.',
                            })
                        }
                        onError={(errors) => {
                            if (errors.password) {
                                passwordInput.current?.focus();
                            }

                            if (errors.current_password) {
                                currentPasswordInput.current?.focus();
                            }
                        }}
                    >
                        {({ errors, processing, isDirty }) => (
                            <FieldGroup className="p-6 sm:p-7">
                                <UnsavedChangesGuard isDirty={isDirty} />
                                {props.hasPassword && (
                                    <Field
                                        data-invalid={Boolean(
                                            errors.current_password,
                                        )}
                                    >
                                        <FieldLabel htmlFor="current_password">
                                            Current password
                                        </FieldLabel>
                                        <PasswordInput
                                            id="current_password"
                                            ref={currentPasswordInput}
                                            name="current_password"
                                            autoComplete="current-password"
                                            placeholder="Enter your current password"
                                            aria-invalid={Boolean(
                                                errors.current_password,
                                            )}
                                        />
                                        <FieldError>
                                            {errors.current_password}
                                        </FieldError>
                                    </Field>
                                )}

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        data-invalid={Boolean(errors.password)}
                                    >
                                        <FieldLabel htmlFor="password">
                                            New password
                                        </FieldLabel>
                                        <PasswordInput
                                            id="password"
                                            ref={passwordInput}
                                            name="password"
                                            autoComplete="new-password"
                                            placeholder="Create a new password"
                                            passwordrules={props.passwordRules}
                                            aria-invalid={Boolean(
                                                errors.password,
                                            )}
                                        />
                                        <FieldError>
                                            {errors.password}
                                        </FieldError>
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
                                            autoComplete="new-password"
                                            placeholder="Repeat your new password"
                                            passwordrules={props.passwordRules}
                                            aria-invalid={Boolean(
                                                errors.password_confirmation,
                                            )}
                                        />
                                        <FieldError>
                                            {errors.password_confirmation}
                                        </FieldError>
                                    </Field>
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={processing || !isDirty}
                                        data-test="update-password-button"
                                        className="w-full sm:w-auto"
                                    >
                                        {processing && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        {props.hasPassword
                                            ? 'Update password'
                                            : 'Set password'}
                                    </Button>
                                </div>
                            </FieldGroup>
                        )}
                    </Form>
                </SettingsPanel>

                {props.canManageGoogle ? (
                    <SettingsPanel
                        variant="inset"
                        title="Connected accounts"
                        description="Log in with an account you already use, without typing your password."
                    >
                        <div className="p-6 sm:p-7">
                            <ManageGoogleAccount
                                googleConnected={props.googleConnected}
                                hasPassword={props.hasPassword}
                            />
                        </div>
                    </SettingsPanel>
                ) : null}

                {props.canManageTwoFactor ? (
                    <SettingsPanel
                        variant="inset"
                        title="Two-factor authentication"
                        description="Add a second step to sign-in with an authenticator app."
                    >
                        <div className="p-6 sm:p-7">
                            <ManageTwoFactor
                                canManageTwoFactor={props.canManageTwoFactor}
                                requiresConfirmation={
                                    props.requiresConfirmation
                                }
                                twoFactorEnabled={props.twoFactorEnabled}
                            />
                        </div>
                    </SettingsPanel>
                ) : null}

                {props.canManagePasskeys ? (
                    <SettingsPanel
                        variant="inset"
                        title="Passkeys"
                        description="Sign in without a password using this device."
                    >
                        <div className="p-6 sm:p-7">
                            <ManagePasskeys
                                canManagePasskeys={props.canManagePasskeys}
                                passkeys={props.passkeys}
                            />
                        </div>
                    </SettingsPanel>
                ) : null}

                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <HugeiconsIcon icon={Shield01Icon} className="size-4" />
                    Your security settings are only available after password
                    confirmation.
                </div>
            </div>
        </>
    );
}
