import { Radio } from '@base-ui/react/radio';
import { RadioGroup } from '@base-ui/react/radio-group';
import {
    ArrowDown01Icon,
    ArrowLeft01Icon,
    Copy01Icon,
    Delete02Icon,
    Image01Icon,
    LinkSquare02Icon,
    MoreHorizontalIcon,
    Share08Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ChangeEvent, ReactNode } from 'react';
import { SubscribeFormView } from '@/components/subscribe-form-view';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAppearance } from '@/hooks/use-appearance';
import {
    firstUploadErrorMessage,
    useUploadToast,
} from '@/hooks/use-upload-toast';
import { teamBrandFonts, teamBrandPalettes } from '@/lib/team-brand-theme';
import { cn } from '@/lib/utils';
import { show as showAudience } from '@/routes/audiences';
import {
    destroy,
    publish,
    unpublish,
    update,
} from '@/routes/audiences/subscribe_forms';
import type {
    AudienceAttribute,
    SubscribeForm,
    SubscribeFormFieldMode,
    SubscribeFormImageSide,
    SubscribeFormLogoShape,
    SubscribeFormLogoSize,
    SubscribeFormStyle,
    SubscribeFormTextAlignment,
} from '@/types/audiences';
import type {
    TeamBrandColor,
    TeamBrandFont,
    TeamBrandInputStyle,
} from '@/types/teams';

type StyleOption = {
    value: SubscribeFormStyle;
    label: string;
    description: string;
};

type BrandOption = {
    value: string;
    label: string;
};

type Props = {
    audience: {
        uuid: string;
        name: string;
        first_name_mode: SubscribeFormFieldMode;
        last_name_mode: SubscribeFormFieldMode;
    };
    subscribeForm: SubscribeForm;
    styles: StyleOption[];
    brandColors: BrandOption[];
    brandFonts: BrandOption[];
    brandInputStyles: BrandOption[];
    attributes: AudienceAttribute[];
    canManage: boolean;
    currentTeam: { slug: string };
};

const SUBSCRIBE_FORM_IMAGE_MAX_BYTES = 2 * 1024 * 1024;

export default function SubscribeFormEdit({
    audience,
    subscribeForm,
    styles,
    brandColors,
    brandFonts,
    brandInputStyles,
    attributes,
    canManage,
    currentTeam,
}: Props) {
    const [shareOpen, setShareOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [imagePreview, setImagePreview] = useState<string | undefined>();
    const [logoPreview, setLogoPreview] = useState<string | undefined>();
    const { resolvedAppearance } = useAppearance();
    const uploadToast = useUploadToast();
    const form = useForm({
        name: subscribeForm.name,
        headline: subscribeForm.headline,
        description: subscribeForm.description || '',
        text_alignment: subscribeForm.text_alignment,
        button_label: subscribeForm.button_label,
        success_heading: subscribeForm.success_heading,
        success_message: subscribeForm.success_message,
        consent_text: subscribeForm.consent_text,
        style: subscribeForm.style,
        image_side: subscribeForm.image_side,
        image: null as File | null,
        remove_image: false,
        logo: null as File | null,
        logo_shape: subscribeForm.logo_shape,
        logo_size: subscribeForm.logo_size,
        remove_logo: false,
        brand_color: subscribeForm.theme.color,
        brand_font: subscribeForm.theme.font,
        brand_input_style: subscribeForm.theme.inputStyle,
    });
    const displayedLogo = form.data.remove_logo
        ? null
        : (logoPreview ?? subscribeForm.logo);
    const displayedImage = form.data.remove_image
        ? null
        : form.data.image || subscribeForm.image_processing
          ? (imagePreview ?? subscribeForm.image_url)
          : subscribeForm.image_url;
    const usesArtwork =
        form.data.style === 'split' || form.data.style === 'cover';
    const routeArgs = [currentTeam.slug, audience.uuid, subscribeForm.uuid] as [
        string,
        string,
        string,
    ];
    const { start, stop } = usePoll(
        2000,
        {
            only: ['subscribeForm'],
        },
        { autoStart: false },
    );

    useEffect(() => {
        if (subscribeForm.image_processing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [start, stop, subscribeForm.image_processing]);

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        const hasImageUpload = usesArtwork && form.data.image !== null;
        const hasLogoUpload = form.data.logo !== null;
        const hasUpload = hasImageUpload || hasLogoUpload;
        const savedData = {
            ...form.data,
            image: null,
            remove_image: false,
            logo: null,
            remove_logo: false,
        };
        form.transform((data) => ({
            ...data,
            image: usesArtwork ? (data.image ?? undefined) : undefined,
            remove_image: usesArtwork && data.remove_image,
            logo: data.logo ?? undefined,
        }));
        form.post(update.form(routeArgs).action, {
            preserveScroll: true,
            forceFormData: hasUpload,
            onStart: () => {
                if (!hasUpload) {
                    return;
                }

                uploadToast.begin({
                    title:
                        hasImageUpload && hasLogoUpload
                            ? 'Uploading artwork and logo…'
                            : hasImageUpload
                              ? 'Uploading artwork…'
                              : 'Uploading logo…',
                });
            },
            onProgress: (progress) => uploadToast.setProgress(progress),
            onSuccess: () => {
                uploadToast.dismiss();
                setLogoPreview(undefined);

                if (!usesArtwork) {
                    setImagePreview(undefined);
                }

                form.setData(savedData);
                form.setDefaults(savedData);
            },
            onError: (errors) =>
                uploadToast.fail({
                    title: firstUploadErrorMessage(
                        errors,
                        'Failed to upload subscribe form images.',
                        hasImageUpload ? 'image' : 'logo',
                    ),
                }),
            onHttpException: () => {
                uploadToast.fail({
                    title: 'Failed to upload subscribe form images.',
                });
            },
            onNetworkError: () => {
                uploadToast.fail({
                    title: 'Failed to upload subscribe form images.',
                });
            },
            onCancel: () => uploadToast.fail({ title: 'Upload cancelled.' }),
        });
    };

    const handleLogoChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (file.size > SUBSCRIBE_FORM_IMAGE_MAX_BYTES) {
            form.setError('logo', 'Logo must be 2 MB or smaller.');
            event.target.value = '';

            return;
        }

        form.clearErrors('logo');

        form.setData((data) => ({
            ...data,
            logo: file,
            remove_logo: false,
        }));
        setLogoPreview(URL.createObjectURL(file));
    };

    const handleImageChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (file.size > SUBSCRIBE_FORM_IMAGE_MAX_BYTES) {
            form.setError('image', 'Artwork must be 2 MB or smaller.');
            event.target.value = '';

            return;
        }

        form.clearErrors('image');

        form.setData((data) => ({
            ...data,
            image: file,
            remove_image: false,
        }));
        setImagePreview(URL.createObjectURL(file));
    };

    const removeImage = () => {
        form.setData((data) => ({
            ...data,
            image: null,
            remove_image: true,
        }));
        setImagePreview(undefined);
    };

    const removeLogo = () => {
        form.setData((data) => ({
            ...data,
            logo: null,
            remove_logo: true,
        }));
        setLogoPreview(undefined);
    };

    const handleStyleChange = (style: SubscribeFormStyle) => {
        if (style === 'split' || style === 'cover') {
            form.setData('style', style);

            return;
        }

        form.clearErrors('image');
        form.setData((data) => ({
            ...data,
            style,
            image: null,
            remove_image: false,
        }));
        setImagePreview(undefined);
    };

    return (
        <>
            <Head title={`Edit ${subscribeForm.name}`} />
            <div className="flex h-dvh min-h-0 w-full flex-col overflow-hidden bg-muted/30">
                <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b bg-background px-3 sm:px-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <Link
                            href={showAudience([
                                currentTeam.slug,
                                audience.uuid,
                            ])}
                            aria-label={`Back to ${audience.name}`}
                            className={buttonVariants({
                                variant: 'ghost',
                                size: 'icon-sm',
                            })}
                        >
                            <HugeiconsIcon icon={ArrowLeft01Icon} />
                        </Link>
                        <Separator
                            orientation="vertical"
                            className="hidden h-5 sm:block"
                        />
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {form.data.name || subscribeForm.name}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {audience.name}
                            </p>
                        </div>
                        <Badge
                            data-test="subscribe-form-status"
                            variant={
                                subscribeForm.published
                                    ? 'success'
                                    : 'secondary'
                            }
                        >
                            {subscribeForm.published ? 'Published' : 'Draft'}
                        </Badge>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        {canManage && (
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        subscribeForm.published
                                            ? 'outline'
                                            : 'default'
                                    }
                                    onClick={() =>
                                        router.patch(
                                            subscribeForm.published
                                                ? unpublish.url(routeArgs)
                                                : publish.url(routeArgs),
                                        )
                                    }
                                >
                                    {subscribeForm.published
                                        ? 'Unpublish'
                                        : 'Publish'}
                                </Button>
                                <Button
                                    form="subscribe-form-editor"
                                    type="submit"
                                    size="sm"
                                    data-test="save-subscribe-form"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    {form.processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Save changes
                                </Button>
                            </>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                render={
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        aria-label="Form actions"
                                        data-test="subscribe-form-actions"
                                    />
                                }
                            >
                                <HugeiconsIcon icon={MoreHorizontalIcon} />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuGroup>
                                    {subscribeForm.published && (
                                        <DropdownMenuItem
                                            render={
                                                <a
                                                    href={
                                                        subscribeForm.public_url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                />
                                            }
                                        >
                                            <HugeiconsIcon
                                                icon={LinkSquare02Icon}
                                            />
                                            Open form
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuItem
                                        onClick={() => setShareOpen(true)}
                                    >
                                        <HugeiconsIcon icon={Share08Icon} />
                                        Share &amp; embed
                                    </DropdownMenuItem>
                                    {canManage && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() =>
                                                    setDeleteOpen(true)
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={Delete02Icon}
                                                />
                                                Delete
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                <div className="flex min-h-0 flex-1 flex-col lg:flex-row lg:overflow-hidden">
                    <div className="flex min-h-[28rem] min-w-0 flex-1 items-center justify-center overflow-hidden p-4 sm:p-6 lg:h-full lg:min-h-0 lg:p-0">
                        <div
                            className={cn(
                                'min-h-[36rem] overflow-x-hidden rounded-xl border bg-background shadow-sm lg:h-full lg:min-h-0 lg:max-w-none lg:overflow-y-auto lg:rounded-none lg:border-0 lg:shadow-none',
                                usesArtwork
                                    ? 'w-full max-w-5xl'
                                    : 'w-full max-w-md',
                            )}
                            data-test="subscribe-form-preview"
                        >
                            <SubscribeFormView
                                form={{
                                    ...form.data,
                                    theme: {
                                        color: form.data.brand_color,
                                        font: form.data.brand_font,
                                        inputStyle: form.data.brand_input_style,
                                    },
                                    description: form.data.description || null,
                                    image_url: displayedImage,
                                    logo: displayedLogo,
                                    first_name_mode: audience.first_name_mode,
                                    last_name_mode: audience.last_name_mode,
                                    attributes,
                                }}
                                preview
                                className="min-h-[36rem] lg:min-h-full"
                            />
                        </div>
                    </div>

                    <aside className="flex min-h-0 w-full shrink-0 flex-col border-t bg-background lg:h-full lg:w-[26rem] lg:overflow-y-auto lg:border-t-0 lg:border-l">
                        <div className="border-b px-4 py-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">
                                    {form.data.name || subscribeForm.name}
                                </p>
                            </div>
                        </div>

                        <form
                            id="subscribe-form-editor"
                            onSubmit={save}
                            className="flex flex-col"
                        >
                            <div className="p-4 sm:p-5">
                                <FieldGroup className="gap-3">
                                    <InspectorSection
                                        title="Layout"
                                        description="Choose how the hosted form looks."
                                        testId="subscribe-form-section-layout"
                                    >
                                        <RadioGroup
                                            className="grid grid-cols-2 gap-2"
                                            name="style"
                                            value={form.data.style}
                                            onValueChange={(value) =>
                                                handleStyleChange(
                                                    value as SubscribeFormStyle,
                                                )
                                            }
                                            disabled={!canManage}
                                            aria-label="Form layout"
                                        >
                                            {styles.map((option) => (
                                                <Radio.Root
                                                    key={option.value}
                                                    data-test={`subscribe-form-style-${option.value}`}
                                                    value={option.value}
                                                    className={cn(
                                                        'flex cursor-pointer flex-col gap-2 rounded-lg border p-2 text-left transition-colors outline-none hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring/50 data-[checked]:border-foreground data-[checked]:bg-muted data-[checked]:ring-1 data-[checked]:ring-foreground data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50',
                                                    )}
                                                >
                                                    <StyleThumbnail
                                                        style={option.value}
                                                        imageSide={
                                                            form.data.image_side
                                                        }
                                                    />
                                                    <span className="text-xs font-medium">
                                                        {option.label}
                                                    </span>
                                                </Radio.Root>
                                            ))}
                                        </RadioGroup>

                                        {form.data.style === 'split' && (
                                            <Field>
                                                <FieldLabel>
                                                    Image side
                                                </FieldLabel>
                                                <Tabs
                                                    className="w-full"
                                                    value={form.data.image_side}
                                                    onValueChange={(value) => {
                                                        if (value) {
                                                            form.setData(
                                                                'image_side',
                                                                value as SubscribeFormImageSide,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <TabsList
                                                        className="w-full"
                                                        variant="sliding"
                                                        data-test="subscribe-form-image-side"
                                                    >
                                                        <TabsTrigger
                                                            className="flex-1"
                                                            disabled={
                                                                !canManage
                                                            }
                                                            value="left"
                                                        >
                                                            Left
                                                        </TabsTrigger>
                                                        <TabsTrigger
                                                            className="flex-1"
                                                            disabled={
                                                                !canManage
                                                            }
                                                            value="right"
                                                        >
                                                            Right
                                                        </TabsTrigger>
                                                    </TabsList>
                                                </Tabs>
                                            </Field>
                                        )}

                                        <Field>
                                            <FieldLabel>
                                                Text alignment
                                            </FieldLabel>
                                            <Tabs
                                                className="w-full"
                                                value={form.data.text_alignment}
                                                onValueChange={(value) => {
                                                    if (value) {
                                                        form.setData(
                                                            'text_alignment',
                                                            value as SubscribeFormTextAlignment,
                                                        );
                                                    }
                                                }}
                                            >
                                                <TabsList
                                                    className="w-full"
                                                    variant="sliding"
                                                    data-test="subscribe-form-text-alignment"
                                                >
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="left"
                                                    >
                                                        Left
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="center"
                                                    >
                                                        Center
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="right"
                                                    >
                                                        Right
                                                    </TabsTrigger>
                                                </TabsList>
                                            </Tabs>
                                            <FieldDescription>
                                                Applies to the form copy and
                                                confirmation message. Logo
                                                placement follows the selected
                                                layout.
                                            </FieldDescription>
                                        </Field>
                                    </InspectorSection>

                                    <InspectorSection
                                        title="Brand theme"
                                        description="Customize the color, typography, and fields for this form."
                                        testId="subscribe-form-section-brand-theme"
                                    >
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.brand_color,
                                            )}
                                        >
                                            <FieldLabel htmlFor="brand-color">
                                                Button color
                                            </FieldLabel>
                                            <Select
                                                items={brandColors}
                                                value={form.data.brand_color}
                                                disabled={!canManage}
                                                onValueChange={(value) => {
                                                    if (value !== null) {
                                                        form.setData(
                                                            'brand_color',
                                                            value as TeamBrandColor,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="brand-color"
                                                    className="w-full"
                                                    aria-invalid={Boolean(
                                                        form.errors.brand_color,
                                                    )}
                                                    data-test="subscribe-form-brand-color"
                                                >
                                                    <span className="flex items-center gap-2">
                                                        <span
                                                            aria-hidden="true"
                                                            className="size-3 rounded-full border border-black/10 dark:border-white/10"
                                                            style={{
                                                                backgroundColor:
                                                                    teamBrandPalettes[
                                                                        form
                                                                            .data
                                                                            .brand_color
                                                                    ][
                                                                        resolvedAppearance
                                                                    ],
                                                            }}
                                                        />
                                                        <SelectValue />
                                                    </span>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {brandColors.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    <span
                                                                        aria-hidden="true"
                                                                        className="size-3 rounded-full border border-black/10 dark:border-white/10"
                                                                        style={{
                                                                            backgroundColor:
                                                                                teamBrandPalettes[
                                                                                    option.value as TeamBrandColor
                                                                                ][
                                                                                    resolvedAppearance
                                                                                ],
                                                                        }}
                                                                    />
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError>
                                                {form.errors.brand_color}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.brand_font,
                                            )}
                                        >
                                            <FieldLabel htmlFor="brand-font">
                                                Font
                                            </FieldLabel>
                                            <Select
                                                items={brandFonts}
                                                value={form.data.brand_font}
                                                disabled={!canManage}
                                                onValueChange={(value) => {
                                                    if (value !== null) {
                                                        form.setData(
                                                            'brand_font',
                                                            value as TeamBrandFont,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="brand-font"
                                                    className="w-full"
                                                    aria-invalid={Boolean(
                                                        form.errors.brand_font,
                                                    )}
                                                    data-test="subscribe-form-brand-font"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {brandFonts.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                    style={{
                                                                        fontFamily:
                                                                            teamBrandFonts[
                                                                                option.value as TeamBrandFont
                                                                            ],
                                                                    }}
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError>
                                                {form.errors.brand_font}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.brand_input_style,
                                            )}
                                        >
                                            <FieldLabel htmlFor="brand-input-style">
                                                Input fields
                                            </FieldLabel>
                                            <Select
                                                items={brandInputStyles}
                                                value={
                                                    form.data.brand_input_style
                                                }
                                                disabled={!canManage}
                                                onValueChange={(value) => {
                                                    if (value !== null) {
                                                        form.setData(
                                                            'brand_input_style',
                                                            value as TeamBrandInputStyle,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="brand-input-style"
                                                    className="w-full"
                                                    aria-invalid={Boolean(
                                                        form.errors
                                                            .brand_input_style,
                                                    )}
                                                    data-test="subscribe-form-brand-input-style"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {brandInputStyles.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError>
                                                {form.errors.brand_input_style}
                                            </FieldError>
                                        </Field>
                                    </InspectorSection>

                                    {usesArtwork && (
                                        <InspectorSection
                                            title="Artwork"
                                            description="JPG, PNG, or WEBP up to 2 MB. We optimize uploads to WebP in the background."
                                            testId="subscribe-form-section-artwork"
                                        >
                                            <Field
                                                data-invalid={Boolean(
                                                    form.errors.image,
                                                )}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
                                                        {displayedImage ? (
                                                            <img
                                                                src={
                                                                    displayedImage
                                                                }
                                                                alt=""
                                                                className="size-full object-cover"
                                                            />
                                                        ) : (
                                                            <HugeiconsIcon
                                                                icon={
                                                                    Image01Icon
                                                                }
                                                                className="size-4 text-muted-foreground"
                                                            />
                                                        )}
                                                    </div>
                                                    <input
                                                        id="image"
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        className="sr-only"
                                                        onChange={
                                                            handleImageChange
                                                        }
                                                        data-test="subscribe-form-image-input"
                                                        disabled={!canManage}
                                                    />
                                                    {canManage && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            nativeButton={false}
                                                            render={
                                                                <label
                                                                    htmlFor="image"
                                                                    className="cursor-pointer"
                                                                />
                                                            }
                                                        >
                                                            {displayedImage
                                                                ? 'Replace image'
                                                                : 'Choose image'}
                                                        </Button>
                                                    )}
                                                    {canManage &&
                                                        displayedImage && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={
                                                                    removeImage
                                                                }
                                                                data-test="subscribe-form-remove-image"
                                                            >
                                                                Remove
                                                            </Button>
                                                        )}
                                                </div>
                                                {subscribeForm.image_processing && (
                                                    <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <Spinner className="size-3" />
                                                        Optimizing image…
                                                    </p>
                                                )}
                                                <FieldError>
                                                    {form.errors.image}
                                                </FieldError>
                                            </Field>
                                        </InspectorSection>
                                    )}

                                    <InspectorSection
                                        title="Logo"
                                        description="Optional. Placement follows the selected layout."
                                        testId="subscribe-form-section-logo"
                                    >
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.logo,
                                            )}
                                        >
                                            <div className="flex items-center gap-3">
                                                <div
                                                    className={cn(
                                                        'flex shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted',
                                                        form.data.logo_shape ===
                                                            'square'
                                                            ? 'size-12'
                                                            : 'h-10 w-16',
                                                    )}
                                                >
                                                    {displayedLogo ? (
                                                        <img
                                                            src={displayedLogo}
                                                            alt=""
                                                            className={cn(
                                                                'max-h-full max-w-full',
                                                                form.data
                                                                    .logo_shape ===
                                                                    'square'
                                                                    ? 'size-full object-cover'
                                                                    : 'object-contain',
                                                            )}
                                                        />
                                                    ) : (
                                                        <HugeiconsIcon
                                                            icon={Image01Icon}
                                                            className="size-4 text-muted-foreground"
                                                        />
                                                    )}
                                                </div>
                                                <input
                                                    id="logo"
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    className="sr-only"
                                                    disabled={!canManage}
                                                    onChange={handleLogoChange}
                                                    data-test="subscribe-form-logo-input"
                                                />
                                                {canManage && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        nativeButton={false}
                                                        render={
                                                            <label
                                                                htmlFor="logo"
                                                                className="cursor-pointer"
                                                            />
                                                        }
                                                    >
                                                        {displayedLogo
                                                            ? 'Replace logo'
                                                            : 'Choose logo'}
                                                    </Button>
                                                )}
                                                {canManage && displayedLogo && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={removeLogo}
                                                        data-test="subscribe-form-remove-logo"
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                            <FieldDescription>
                                                JPG, PNG, or WEBP up to 2 MB.
                                            </FieldDescription>
                                            <FieldError>
                                                {form.errors.logo}
                                            </FieldError>
                                        </Field>
                                        <Field>
                                            <FieldLabel>Shape</FieldLabel>
                                            <Tabs
                                                className="w-full"
                                                value={form.data.logo_shape}
                                                onValueChange={(value) => {
                                                    if (value) {
                                                        form.setData(
                                                            'logo_shape',
                                                            value as SubscribeFormLogoShape,
                                                        );
                                                    }
                                                }}
                                            >
                                                <TabsList
                                                    className="w-full"
                                                    variant="sliding"
                                                    data-test="subscribe-form-logo-shape"
                                                >
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="default"
                                                    >
                                                        Default
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="square"
                                                    >
                                                        Square
                                                    </TabsTrigger>
                                                </TabsList>
                                            </Tabs>
                                            <FieldDescription>
                                                Square crops to a tile. Default
                                                keeps the original orientation.
                                            </FieldDescription>
                                        </Field>
                                        <Field>
                                            <FieldLabel>Size</FieldLabel>
                                            <Tabs
                                                className="w-full"
                                                value={form.data.logo_size}
                                                onValueChange={(value) => {
                                                    if (value) {
                                                        form.setData(
                                                            'logo_size',
                                                            value as SubscribeFormLogoSize,
                                                        );
                                                    }
                                                }}
                                            >
                                                <TabsList
                                                    className="w-full"
                                                    variant="sliding"
                                                    data-test="subscribe-form-logo-size"
                                                >
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="small"
                                                    >
                                                        Small
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="medium"
                                                    >
                                                        Medium
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        className="flex-1"
                                                        disabled={!canManage}
                                                        value="large"
                                                    >
                                                        Large
                                                    </TabsTrigger>
                                                </TabsList>
                                            </Tabs>
                                        </Field>
                                    </InspectorSection>

                                    <InspectorSection
                                        title="Content"
                                        description="Write the copy visitors see before and after they subscribe."
                                        testId="subscribe-form-section-content"
                                    >
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.name,
                                            )}
                                        >
                                            <FieldLabel htmlFor="name">
                                                Internal name
                                            </FieldLabel>
                                            <Input
                                                id="name"
                                                value={form.data.name}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                                placeholder="Website footer"
                                                aria-invalid={Boolean(
                                                    form.errors.name,
                                                )}
                                            />
                                            <FieldDescription>
                                                Visible only to your team.
                                            </FieldDescription>
                                            <FieldError>
                                                {form.errors.name}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.headline,
                                            )}
                                        >
                                            <FieldLabel htmlFor="headline">
                                                Headline
                                            </FieldLabel>
                                            <Input
                                                id="headline"
                                                value={form.data.headline}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'headline',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                                placeholder="Join our newsletter"
                                                aria-invalid={Boolean(
                                                    form.errors.headline,
                                                )}
                                            />
                                            <FieldError>
                                                {form.errors.headline}
                                            </FieldError>
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor="description">
                                                Description
                                            </FieldLabel>
                                            <Textarea
                                                id="description"
                                                value={form.data.description}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'description',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                                placeholder="A short monthly update."
                                                rows={3}
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor="button-label">
                                                Button label
                                            </FieldLabel>
                                            <Input
                                                id="button-label"
                                                value={form.data.button_label}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'button_label',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                                placeholder="Subscribe"
                                            />
                                        </Field>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.success_heading,
                                            )}
                                        >
                                            <FieldLabel htmlFor="success-heading">
                                                Success heading
                                            </FieldLabel>
                                            <Input
                                                id="success-heading"
                                                value={
                                                    form.data.success_heading
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'success_heading',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                                placeholder="You’re subscribed!"
                                                aria-invalid={Boolean(
                                                    form.errors.success_heading,
                                                )}
                                            />
                                            <FieldError>
                                                {form.errors.success_heading}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors.success_message,
                                            )}
                                        >
                                            <FieldLabel htmlFor="success-message">
                                                Success message
                                            </FieldLabel>
                                            <Textarea
                                                id="success-message"
                                                value={
                                                    form.data.success_message
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'success_message',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                                placeholder="Thanks for subscribing!"
                                                rows={3}
                                                aria-invalid={Boolean(
                                                    form.errors.success_message,
                                                )}
                                            />
                                            <FieldError>
                                                {form.errors.success_message}
                                            </FieldError>
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor="consent-text">
                                                Consent text
                                            </FieldLabel>
                                            <Textarea
                                                id="consent-text"
                                                value={form.data.consent_text}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'consent_text',
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={!canManage}
                                                placeholder="I agree to receive marketing emails."
                                                rows={3}
                                            />
                                        </Field>
                                    </InspectorSection>
                                </FieldGroup>
                            </div>
                        </form>
                    </aside>
                </div>
            </div>

            <ShareEmbedDialog
                open={shareOpen}
                onOpenChange={setShareOpen}
                subscribeForm={subscribeForm}
            />

            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete this form?</AlertDialogTitle>
                        <AlertDialogDescription>
                            The hosted page will stop working. Existing
                            subscriber attribution is preserved.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroy.url(routeArgs))
                            }
                        >
                            Delete form
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function InspectorSection({
    title,
    description,
    testId,
    children,
}: {
    title: string;
    description: string;
    testId: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(true);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="w-full rounded-xl border bg-card shadow-xs"
            data-test={testId}
        >
            <FieldSet className="gap-0">
                <FieldLegend className="mb-0 w-full">
                    <CollapsibleTrigger
                        render={
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-auto w-full items-start justify-between rounded-xl px-3 py-3 text-left whitespace-normal"
                            />
                        }
                    >
                        <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span>{title}</span>
                            <span className="text-xs leading-snug font-normal text-muted-foreground">
                                {description}
                            </span>
                        </span>
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-background shadow-xs ring-1 ring-border">
                            <HugeiconsIcon
                                icon={ArrowDown01Icon}
                                data-icon="inline-end"
                                className={cn(
                                    'text-muted-foreground transition-transform duration-200',
                                    !open && '-rotate-90',
                                )}
                            />
                        </span>
                    </CollapsibleTrigger>
                </FieldLegend>
                <CollapsibleContent className="h-(--collapsible-panel-height) overflow-hidden transition-[height,opacity] duration-300 ease-out data-ending-style:h-0 data-ending-style:opacity-0 data-starting-style:h-0 data-starting-style:opacity-0 motion-reduce:transition-none">
                    <Separator />
                    <div className="flex flex-col gap-6 px-3 py-4">
                        {children}
                    </div>
                </CollapsibleContent>
            </FieldSet>
        </Collapsible>
    );
}

function StyleThumbnail({
    style,
    imageSide,
}: {
    style: SubscribeFormStyle;
    imageSide: SubscribeFormImageSide;
}) {
    if (style === 'split') {
        return (
            <div className="grid h-16 grid-cols-2 overflow-hidden rounded-md bg-muted">
                {imageSide === 'left' ? (
                    <div className="bg-zinc-900" />
                ) : (
                    <div className="m-1.5 rounded-sm bg-background" />
                )}
                {imageSide === 'right' ? (
                    <div className="bg-zinc-900" />
                ) : (
                    <div className="m-1.5 rounded-sm bg-background" />
                )}
            </div>
        );
    }

    if (style === 'minimal') {
        return (
            <div className="flex h-16 items-center justify-center rounded-md bg-background ring-1 ring-border">
                <div className="flex w-10 flex-col gap-1">
                    <div className="h-1 rounded-full bg-foreground/70" />
                    <div className="h-2 rounded-sm bg-muted" />
                    <div className="h-2 rounded-sm bg-muted" />
                </div>
            </div>
        );
    }

    if (style === 'cover') {
        return (
            <div className="relative flex h-16 items-center justify-center overflow-hidden rounded-md bg-zinc-900">
                <div className="relative h-8 w-8 rounded-sm bg-background" />
            </div>
        );
    }

    return (
        <div className="flex h-16 items-center justify-center rounded-md bg-muted">
            <div className="h-10 w-8 rounded-sm bg-background shadow-xs" />
        </div>
    );
}

function ShareEmbedDialog({
    open,
    onOpenChange,
    subscribeForm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    subscribeForm: SubscribeForm;
}) {
    const [copied, setCopied] = useState<'url' | 'embed' | null>(null);

    const copy = async (kind: 'url' | 'embed', value: string) => {
        await navigator.clipboard.writeText(value);
        setCopied(kind);
        window.setTimeout(() => setCopied(null), 1600);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Share &amp; embed</DialogTitle>
                    <DialogDescription>
                        {subscribeForm.published
                            ? 'Share the hosted page or embed the form on another site.'
                            : 'Publish the form before sharing these links.'}
                    </DialogDescription>
                </DialogHeader>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="public-url">Hosted URL</FieldLabel>
                        <div className="flex gap-2">
                            <Input
                                id="public-url"
                                readOnly
                                value={subscribeForm.public_url}
                                placeholder="https://example.com/forms/…"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                aria-label="Copy hosted URL"
                                onClick={() =>
                                    copy('url', subscribeForm.public_url)
                                }
                            >
                                <HugeiconsIcon icon={Copy01Icon} />
                            </Button>
                        </div>
                        {copied === 'url' && (
                            <FieldDescription>
                                Copied to clipboard.
                            </FieldDescription>
                        )}
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="embed-code">
                            Iframe embed
                        </FieldLabel>
                        <Textarea
                            id="embed-code"
                            readOnly
                            value={subscribeForm.embed_code}
                            placeholder="<iframe src=…>"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                copy('embed', subscribeForm.embed_code)
                            }
                        >
                            <HugeiconsIcon
                                icon={Copy01Icon}
                                data-icon="inline-start"
                            />
                            {copied === 'embed' ? 'Copied' : 'Copy embed code'}
                        </Button>
                    </Field>
                </FieldGroup>
            </DialogContent>
        </Dialog>
    );
}

SubscribeFormEdit.layout = null;
