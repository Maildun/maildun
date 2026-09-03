import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import PasswordInput from '@/components/password-input';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <SettingsPanel
            variant="inset"
            title="Delete account"
            description="Permanently remove your account. Teams you own will pass to another admin or member."
        >
            <div className="flex w-full flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
                <p className="text-sm text-muted-foreground">
                    This action is permanent. Your account cannot be recovered
                    after deletion.
                </p>

                <Dialog>
                    <DialogTrigger
                        render={
                            <Button
                                variant="destructive"
                                data-test="delete-user-button"
                                className="shrink-0"
                            />
                        }
                    >
                        Delete account
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, teams you own will
                            pass to their longest-standing admin or member, and
                            your personal team will be permanently removed along
                            with its data. Please enter your password to confirm
                            you would like to permanently delete your account.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="flex flex-col gap-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <Field
                                        data-invalid={Boolean(errors.password)}
                                    >
                                        <FieldLabel
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Password
                                        </FieldLabel>

                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Password"
                                            autoComplete="current-password"
                                            aria-invalid={Boolean(
                                                errors.password,
                                            )}
                                        />

                                        <FieldError>
                                            {errors.password}
                                        </FieldError>
                                    </Field>

                                    <DialogFooter className="gap-2">
                                        <DialogClose
                                            render={
                                                <Button
                                                    variant="secondary"
                                                    onClick={() =>
                                                        resetAndClearErrors()
                                                    }
                                                />
                                            }
                                        >
                                            Cancel
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            render={
                                                <button
                                                    type="submit"
                                                    data-test="confirm-delete-user-button"
                                                />
                                            }
                                        >
                                            {processing && <Spinner />}
                                            Delete account
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </SettingsPanel>
    );
}
