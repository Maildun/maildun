import { Camera01Icon, Copy01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { useInitials } from '@/hooks/use-initials';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes';
import {
    firstUploadErrorMessage,
    useUploadToast,
} from '@/hooks/use-upload-toast';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();
    const avatarInput = useRef<HTMLInputElement>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | undefined>();
    const [isCopied, setIsCopied] = useState(false);
    const uploadToast = useUploadToast();
    const displayedAvatar = avatarPreview ?? auth.user.avatar;
    const isEmailVerified = auth.user.email_verified_at !== null;

    useEffect(() => {
        return () => {
            if (avatarPreview) {
                URL.revokeObjectURL(avatarPreview);
            }
        };
    }, [avatarPreview]);

    const handleAvatarChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setAvatarPreview((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return URL.createObjectURL(file);
        });
    };

    const clearAvatarPreview = () => {
        if (avatarInput.current) {
            avatarInput.current.value = '';
        }

        setAvatarPreview((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return undefined;
        });
    };

    const copyUserId = async () => {
        if (!navigator.clipboard) {
            return;
        }

        try {
            await navigator.clipboard.writeText(auth.user.uuid);
            setIsCopied(true);
            window.setTimeout(() => setIsCopied(false), 1600);
        } catch {
            return;
        }
    };

    return (
        <>
            <Head title="Profile" />

            <div
                className="flex flex-col gap-8"
                data-test="profile-settings-panel"
            >
                <SettingsPageHeader title="Profile" />
                <SettingsPanel
                    variant="inset"
                    title="Profile"
                    description="Update your public identity and contact information."
                >
                    <Form
                        {...ProfileController.update.form()}
                        encType="multipart/form-data"
                        options={{
                            preserveScroll: true,
                        }}
                        setDefaultsOnSuccess
                        onStart={() => {
                            if (avatarPreview) {
                                uploadToast.begin({
                                    title: 'Uploading profile photo…',
                                });
                            }
                        }}
                        onProgress={(event) => uploadToast.setProgress(event)}
                        onSuccess={() => {
                            uploadToast.dismiss();
                            setAvatarPreview(undefined);
                            toast.add({
                                type: 'success',
                                title: 'Changes saved.',
                            });
                        }}
                        onError={(errors) => {
                            uploadToast.fail({
                                title: firstUploadErrorMessage(
                                    errors,
                                    'Failed to upload profile photo.',
                                    'avatar',
                                ),
                            });
                            focusFirstInvalidField();
                        }}
                        onHttpException={() => {
                            uploadToast.fail({
                                title: 'Failed to upload profile photo.',
                            });
                        }}
                        onNetworkError={() => {
                            uploadToast.fail({
                                title: 'Failed to upload profile photo.',
                            });
                        }}
                        onCancel={() =>
                            uploadToast.fail({ title: 'Upload cancelled.' })
                        }
                    >
                        {({ processing, errors, isDirty }) => (
                            <FieldGroup className="gap-0 divide-y divide-border">
                                <UnsavedChangesGuard isDirty={isDirty} />
                                <Field
                                    orientation="responsive"
                                    data-invalid={Boolean(errors.avatar)}
                                    className="gap-4 px-6 py-5 sm:px-7 @md/field-group:justify-between"
                                >
                                    <FieldContent>
                                        <FieldLabel htmlFor="avatar">
                                            Profile photo
                                        </FieldLabel>
                                        <FieldDescription className="text-xs">
                                            JPG, PNG, or WEBP up to 2 MB.
                                        </FieldDescription>
                                        <FieldError>{errors.avatar}</FieldError>
                                    </FieldContent>
                                    <div className="flex items-center gap-3">
                                        <label
                                            htmlFor="avatar"
                                            className="group/avatar-upload relative block cursor-pointer rounded-full focus-within:ring-2 focus-within:ring-ring/50"
                                        >
                                            <input
                                                id="avatar"
                                                ref={avatarInput}
                                                name="avatar"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                className="sr-only"
                                                onChange={handleAvatarChange}
                                            />
                                            <Avatar className="size-10">
                                                {displayedAvatar ? (
                                                    <AvatarImage
                                                        src={displayedAvatar}
                                                        alt={auth.user.name}
                                                    />
                                                ) : null}
                                                <AvatarFallback>
                                                    {getInitials(
                                                        auth.user.name,
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span className="pointer-events-none absolute inset-0 flex items-center justify-center rounded-full bg-foreground/50 text-background opacity-0 transition-opacity group-focus-within/avatar-upload:opacity-100 group-hover/avatar-upload:opacity-100">
                                                <HugeiconsIcon
                                                    icon={Camera01Icon}
                                                    className="size-3.5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                        </label>
                                        {avatarPreview ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={clearAvatarPreview}
                                            >
                                                Reset
                                            </Button>
                                        ) : null}
                                    </div>
                                </Field>

                                <Field
                                    orientation="responsive"
                                    data-invalid={Boolean(errors.name)}
                                    className="gap-4 px-6 py-5 sm:px-7 @md/field-group:justify-between"
                                >
                                    <FieldContent>
                                        <FieldLabel htmlFor="name">
                                            Name
                                        </FieldLabel>
                                        <FieldDescription className="text-xs">
                                            Shown across Maildun wherever your
                                            profile appears.
                                        </FieldDescription>
                                    </FieldContent>
                                    <div className="flex w-full flex-col gap-2 @md/field-group:max-w-xs">
                                        <Input
                                            id="name"
                                            defaultValue={auth.user.name}
                                            name="name"
                                            required
                                            autoComplete="name"
                                            placeholder="Jane Cooper"
                                            aria-label="Name"
                                            aria-invalid={Boolean(errors.name)}
                                        />
                                        <FieldError>{errors.name}</FieldError>
                                    </div>
                                </Field>

                                <Field
                                    orientation="responsive"
                                    data-invalid={Boolean(errors.email)}
                                    className="gap-4 px-6 py-5 sm:px-7 @md/field-group:justify-between"
                                >
                                    <FieldContent>
                                        <FieldLabel
                                            htmlFor="email"
                                            className="items-center"
                                        >
                                            Email
                                            <Badge
                                                variant={
                                                    isEmailVerified
                                                        ? 'success'
                                                        : 'secondary'
                                                }
                                            >
                                                {isEmailVerified
                                                    ? 'Verified'
                                                    : 'Unverified'}
                                            </Badge>
                                        </FieldLabel>
                                        <FieldDescription className="text-xs">
                                            Used for sign-in, notifications, and
                                            account recovery.
                                        </FieldDescription>
                                    </FieldContent>
                                    <div className="flex w-full flex-col gap-2 @md/field-group:max-w-xs">
                                        <Input
                                            id="email"
                                            type="email"
                                            defaultValue={auth.user.email}
                                            name="email"
                                            required
                                            autoComplete="username"
                                            placeholder="email@example.com"
                                            aria-label="Email"
                                            aria-invalid={Boolean(errors.email)}
                                        />
                                        <FieldError>{errors.email}</FieldError>
                                        {mustVerifyEmail &&
                                            auth.user.email_verified_at ===
                                                null && (
                                                <div className="flex flex-col gap-2">
                                                    <p className="text-xs text-muted-foreground">
                                                        Your email address is
                                                        unverified.{' '}
                                                        <Link
                                                            href={send()}
                                                            as="button"
                                                            className="text-foreground underline underline-offset-4"
                                                        >
                                                            Resend verification
                                                            email.
                                                        </Link>
                                                    </p>
                                                    {status ===
                                                        'verification-link-sent' && (
                                                        <p className="text-xs font-medium">
                                                            A new verification
                                                            link has been sent
                                                            to your email
                                                            address.
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                    </div>
                                </Field>

                                <Field
                                    orientation="responsive"
                                    className="gap-4 px-6 py-5 sm:px-7 @md/field-group:justify-between"
                                >
                                    <FieldContent>
                                        <FieldLabel>Account ID</FieldLabel>
                                        <FieldDescription className="font-mono text-xs break-all">
                                            {auth.user.uuid}
                                        </FieldDescription>
                                    </FieldContent>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={copyUserId}
                                        aria-live="polite"
                                    >
                                        <HugeiconsIcon icon={Copy01Icon} />
                                        {isCopied ? 'Copied' : 'Copy ID'}
                                    </Button>
                                </Field>

                                <div className="flex items-center justify-end px-6 py-5 sm:px-7">
                                    <Button
                                        type="submit"
                                        disabled={processing || !isDirty}
                                        data-test="update-profile-button"
                                        className="w-full sm:w-auto"
                                    >
                                        {processing && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        Save changes
                                    </Button>
                                </div>
                            </FieldGroup>
                        )}
                    </Form>
                </SettingsPanel>

                <DeleteUser />
            </div>
        </>
    );
}
