import { ArrowLeft01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { index as mediaIndex } from '@/routes/media';
import { edit, update } from '@/routes/media/settings';

type Props = {
    convertUploadsToWebp: boolean;
    canManage: boolean;
};

export default function MediaSettings({
    convertUploadsToWebp,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [convert, setConvert] = useState(convertUploadsToWebp);

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Media settings" />

            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col gap-4">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-fit"
                        nativeButton={false}
                        render={
                            <Link
                                href={mediaIndex(currentTeam.slug)}
                                prefetch
                            />
                        }
                    >
                        <HugeiconsIcon
                            icon={ArrowLeft01Icon}
                            data-icon="inline-start"
                        />
                        Back to media
                    </Button>

                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Media settings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Control how this team stores uploaded images.
                        </p>
                    </div>
                </div>

                <Form
                    {...update.form.patch(currentTeam.slug)}
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-6">
                            <input
                                type="hidden"
                                name="convert_uploads_to_webp"
                                value={convert ? '1' : '0'}
                            />

                            <SettingsPanel
                                title="Optimize uploads"
                                description="JPG and PNG can be converted to WebP in the background after upload. GIF and WebP stay as they are. Existing files are not converted."
                            >
                                <FieldGroup className="p-5">
                                    <Field
                                        orientation="horizontal"
                                        data-invalid={Boolean(
                                            errors.convert_uploads_to_webp,
                                        )}
                                        data-disabled={!canManage}
                                    >
                                        <FieldContent>
                                            <FieldLabel htmlFor="convert-uploads-to-webp">
                                                Convert JPG and PNG to WebP
                                            </FieldLabel>
                                            <FieldDescription>
                                                When this is on, new JPEG and
                                                PNG uploads are optimized in the
                                                background before they appear in
                                                the library.
                                            </FieldDescription>
                                        </FieldContent>
                                        <Switch
                                            id="convert-uploads-to-webp"
                                            checked={convert}
                                            onCheckedChange={setConvert}
                                            disabled={!canManage}
                                            data-test="convert-uploads-switch"
                                            aria-invalid={Boolean(
                                                errors.convert_uploads_to_webp,
                                            )}
                                        />
                                    </Field>
                                    <FieldError>
                                        {errors.convert_uploads_to_webp}
                                    </FieldError>
                                </FieldGroup>
                            </SettingsPanel>

                            {canManage && (
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        data-test="save-media-settings"
                                        disabled={processing}
                                    >
                                        {processing && (
                                            <Spinner data-icon="inline-start" />
                                        )}
                                        Save changes
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

MediaSettings.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Media',
            href: props.currentTeam ? mediaIndex(props.currentTeam.slug) : '/',
        },
        {
            title: 'Settings',
            href: props.currentTeam ? edit(props.currentTeam.slug) : '/',
        },
    ],
});
