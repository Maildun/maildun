import { Form, Head, usePage } from '@inertiajs/react';
import {
    ContinueWithGoogle,
    ContinueWithGoogleDivider,
} from '@/components/continue-with-google';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { confirm as confirmWithGoogle } from '@/routes/google';
import { store } from '@/routes/password/confirm';

type Props = {
    hasPassword: boolean;
    canConfirmWithGoogle: boolean;
};

export default function ConfirmPassword({
    hasPassword,
    canConfirmWithGoogle,
}: Props) {
    const { errors: pageErrors } = usePage().props;
    const googleError = pageErrors.google;

    return (
        <>
            <Head title="Confirm password" />

            <div className="grid gap-6">
                {hasPassword && (
                    <Form {...store.form()} resetOnSuccess={['password']}>
                        {({ processing, errors }) => (
                            <div className="space-y-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        placeholder="Password"
                                        autoComplete="current-password"
                                        autoFocus
                                    />

                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center">
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                        data-test="confirm-password-button"
                                    >
                                        {processing && <Spinner />}
                                        Confirm password
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                )}

                {hasPassword && canConfirmWithGoogle && (
                    <ContinueWithGoogleDivider />
                )}

                {(canConfirmWithGoogle || Boolean(googleError)) && (
                    <div className="grid gap-2">
                        {canConfirmWithGoogle && (
                            <ContinueWithGoogle
                                href={confirmWithGoogle.url()}
                            />
                        )}
                        <InputError
                            message={googleError}
                            data-test="google-error"
                        />
                    </div>
                )}

                {!hasPassword && !canConfirmWithGoogle && (
                    <div className="grid gap-6 text-sm text-muted-foreground">
                        <p>
                            Log out, then reset your password from the login
                            page to set one for your account.
                        </p>

                        <TextLink href={logout()} className="text-center">
                            Log out
                        </TextLink>
                    </div>
                )}
            </div>
        </>
    );
}

ConfirmPassword.layout = ({ hasPassword, canConfirmWithGoogle }: Props) => {
    if (hasPassword) {
        return {
            title: 'Confirm password',
            description:
                'This is a secure area of the application. Please confirm your password before continuing.',
        };
    }

    return {
        title: "Confirm it's you",
        description: canConfirmWithGoogle
            ? 'This is a secure area of the application. Please confirm it is you with the Google account connected to your Maildun account.'
            : 'This is a secure area of the application. Your account does not have a password, and Google sign-in is not available right now.',
    };
};
