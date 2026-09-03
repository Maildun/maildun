import { TeamWorkIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import {
    firstUploadErrorMessage,
    useUploadToast,
} from '@/hooks/use-upload-toast';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import { dashboard } from '@/routes';
import { store } from '@/routes/teams';

export default function CreateWorkspace() {
    const { currentTeam } = usePage().props;
    const logoInput = useRef<HTMLInputElement>(null);
    const [name, setName] = useState('');
    const [logoPreview, setLogoPreview] = useState<string>();
    const uploadToast = useUploadToast();

    useEffect(() => {
        return () => {
            if (logoPreview) {
                URL.revokeObjectURL(logoPreview);
            }
        };
    }, [logoPreview]);

    const handleLogoChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setLogoPreview((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return URL.createObjectURL(file);
        });
    };

    const clearLogo = () => {
        if (logoInput.current) {
            logoInput.current.value = '';
        }

        setLogoPreview((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }

            return undefined;
        });
    };

    const previewName = name.trim() || 'Your workspace';
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    return (
        <>
            <Head title="Create workspace" />

            <main className="min-h-svh bg-muted/45 px-4 py-6 sm:px-6 sm:py-10 dark:bg-background">
                <div className="mx-auto flex min-h-[calc(100svh-3rem)] max-w-7xl flex-col items-center justify-center px-5 py-10 sm:min-h-[calc(100svh-5rem)] sm:px-10 lg:px-16">
                    <Link
                        href={dashboardUrl}
                        aria-label="Back to Maildun"
                        className="mb-8 text-foreground transition-opacity hover:opacity-70"
                    >
                        <AppLogoIcon className="mx-auto h-9 w-9" />
                    </Link>

                    <div className="grid w-full max-w-6xl overflow-hidden rounded-3xl border bg-card shadow-sm lg:min-h-[42rem] lg:grid-cols-2">
                        <Form
                            {...store.form()}
                            encType="multipart/form-data"
                            className="h-full p-6 sm:p-9 lg:p-12"
                            onStart={() => {
                                if (logoPreview) {
                                    uploadToast.begin({
                                        title: 'Uploading workspace logo…',
                                    });
                                }
                            }}
                            onProgress={(event) =>
                                uploadToast.setProgress(event)
                            }
                            onSuccess={() => uploadToast.dismiss()}
                            onError={(errors) => {
                                uploadToast.fail({
                                    title: firstUploadErrorMessage(
                                        errors,
                                        'Failed to upload workspace logo.',
                                        'logo',
                                    ),
                                });
                                focusFirstInvalidField();
                            }}
                            onHttpException={() => {
                                uploadToast.fail({
                                    title: 'Failed to upload workspace logo.',
                                });
                            }}
                            onNetworkError={() => {
                                uploadToast.fail({
                                    title: 'Failed to upload workspace logo.',
                                });
                            }}
                            onCancel={() =>
                                uploadToast.fail({ title: 'Upload cancelled.' })
                            }
                        >
                            {({ errors, processing }) => (
                                <FieldGroup className="h-full gap-6">
                                    <div className="space-y-2">
                                        <h1 className="text-2xl font-semibold tracking-tight">
                                            Create workspace
                                        </h1>
                                        <p className="max-w-md text-sm leading-6 text-muted-foreground">
                                            Give your workspace a name and a
                                            recognizable logo. You can invite
                                            your workspace and finish setup
                                            next.
                                        </p>
                                    </div>

                                    <Field
                                        data-invalid={Boolean(errors.logo)}
                                        className="gap-4"
                                    >
                                        <div className="flex items-center gap-4">
                                            <Avatar className="size-14 rounded-xl border bg-muted after:rounded-xl">
                                                {logoPreview ? (
                                                    <AvatarImage
                                                        src={logoPreview}
                                                        alt="Workspace logo preview"
                                                        className="rounded-xl"
                                                    />
                                                ) : null}
                                                <AvatarFallback className="rounded-xl bg-muted text-muted-foreground">
                                                    <HugeiconsIcon
                                                        icon={TeamWorkIcon}
                                                        className="size-5"
                                                    />
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <input
                                                    ref={logoInput}
                                                    id="logo"
                                                    name="logo"
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    className="peer sr-only"
                                                    onChange={handleLogoChange}
                                                />
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="peer-focus-visible:border-ring peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50"
                                                    nativeButton={false}
                                                    render={
                                                        <label
                                                            htmlFor="logo"
                                                            className="cursor-pointer"
                                                        />
                                                    }
                                                >
                                                    Upload logo
                                                </Button>
                                                {logoPreview ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={clearLogo}
                                                    >
                                                        Remove
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <FieldLabel htmlFor="logo">
                                                Workspace logo
                                            </FieldLabel>
                                            <FieldDescription>
                                                Optional. JPG, PNG, or WEBP up
                                                to 2 MB.
                                            </FieldDescription>
                                            <FieldError>
                                                {errors.logo}
                                            </FieldError>
                                        </div>
                                    </Field>

                                    <Field
                                        data-invalid={Boolean(errors.name)}
                                        className="gap-2"
                                    >
                                        <FieldLabel htmlFor="name">
                                            Workspace name
                                        </FieldLabel>
                                        <Input
                                            id="name"
                                            name="name"
                                            data-test="create-workspace-name"
                                            value={name}
                                            onChange={(event) =>
                                                setName(event.target.value)
                                            }
                                            placeholder="Acme"
                                            autoComplete="organization"
                                            required
                                            aria-invalid={Boolean(errors.name)}
                                        />
                                        <FieldError>{errors.name}</FieldError>
                                    </Field>

                                    <div className="mt-auto pt-2">
                                        <Button
                                            type="submit"
                                            data-test="create-workspace-submit"
                                            disabled={
                                                processing || !name.trim()
                                            }
                                            className="w-full"
                                        >
                                            {processing ? (
                                                <Spinner data-icon="inline-start" />
                                            ) : null}
                                            Create workspace
                                        </Button>
                                    </div>
                                </FieldGroup>
                            )}
                        </Form>

                        <WorkspacePreview
                            name={previewName}
                            logo={logoPreview}
                        />
                    </div>
                </div>
            </main>
        </>
    );
}

function WorkspacePreview({ name, logo }: { name: string; logo?: string }) {
    return (
        <aside className="grainy relative hidden overflow-hidden rounded-l-3xl bg-muted/50 p-7 lg:block lg:p-10">
            <div className="absolute inset-x-0 top-0 h-28 bg-gradient-to-br from-primary/10 via-transparent to-transparent" />
            <div className="relative h-full rounded-2xl border bg-background shadow-sm">
                <div className="flex items-center gap-3 border-b px-5 py-4">
                    <Avatar className="size-7 rounded-md after:rounded-md">
                        {logo ? (
                            <AvatarImage
                                src={logo}
                                alt={`${name} logo`}
                                className="rounded-md"
                            />
                        ) : null}
                        <AvatarFallback className="rounded-md bg-primary text-[10px] font-semibold text-primary-foreground">
                            {name.slice(0, 1).toUpperCase()}
                        </AvatarFallback>
                    </Avatar>
                    <span className="truncate text-sm font-medium">{name}</span>
                </div>
                <div className="space-y-7 p-5">
                    <PreviewSection label="Campaigns" lines={[72, 56, 80]} />
                    <PreviewSection label="Audiences" lines={[64, 76]} />
                    <PreviewSection label="Automations" lines={[82, 50, 68]} />
                </div>
            </div>
        </aside>
    );
}

function PreviewSection({ label, lines }: { label: string; lines: number[] }) {
    return (
        <div className="space-y-3">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <div className="space-y-2.5">
                {lines.map((width, index) => (
                    <div
                        key={`${label}-${width}-${index}`}
                        className="flex items-center gap-2"
                    >
                        <span className="size-2.5 rounded-full bg-muted" />
                        <span
                            className="h-2 rounded-full bg-muted"
                            style={{ width: `${width}%` }}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
