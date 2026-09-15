import { Calendar02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { usePage } from '@inertiajs/react';
import { format, parse } from 'date-fns';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import type { FormEvent, ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SubscribeFormArtworkVisual } from '@/components/subscribe-form-artwork';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { subscribeFormThemeStyle } from '@/lib/team-brand-theme';
import { cn } from '@/lib/utils';
import type { TeamBrandTheme } from '@/types';
import type {
    AudienceAttribute,
    SubscribeFormArtworkPreset,
    SubscribeFormArtworkType,
    SubscribeFormCardPadding,
    SubscribeFormFieldMode,
    SubscribeFormHeaderSpacing,
    SubscribeFormImageSide,
    SubscribeFormLogoPosition,
    SubscribeFormLogoShape,
    SubscribeFormLogoSize,
    SubscribeFormPoweredByPosition,
    SubscribeFormStyle,
    SubscribeFormTextAlignment,
} from '@/types/audiences';

const ATTRIBUTE_DATE_FORMAT = 'yyyy-MM-dd';
const STAGE_TRANSITION = { duration: 0.2, ease: 'easeOut' } as const;

export type SubscribeFormAppearance = {
    theme: TeamBrandTheme;
    headline: string;
    description: string | null;
    text_alignment: SubscribeFormTextAlignment;
    button_label: string;
    success_heading: string;
    powered_by_enabled: boolean;
    powered_by_form_position: SubscribeFormPoweredByPosition;
    consent_text: string;
    style: SubscribeFormStyle;
    image_side: SubscribeFormImageSide;
    artwork_type: SubscribeFormArtworkType;
    artwork_preset: SubscribeFormArtworkPreset | null;
    image_url: string | null;
    logo: string | null;
    logo_shape: SubscribeFormLogoShape;
    logo_size: SubscribeFormLogoSize;
    logo_position: SubscribeFormLogoPosition;
    header_spacing: SubscribeFormHeaderSpacing;
    card_padding: SubscribeFormCardPadding;
    first_name_mode: SubscribeFormFieldMode;
    last_name_mode: SubscribeFormFieldMode;
    attributes: AudienceAttribute[];
};

export type SubscribeFormValues = {
    email: string;
    first_name: string;
    last_name: string;
    consent: boolean;
    attributes: Record<string, string>;
    website?: string;
};

type Props = {
    form: SubscribeFormAppearance;
    preview?: boolean;
    previewViewport?: 'responsive' | 'mobile';
    completed?: boolean;
    successMessage?: string;
    redirectCountdown?: number | null;
    processing?: boolean;
    errors?: Record<string, string | undefined>;
    values?: SubscribeFormValues;
    onChange?: <K extends keyof SubscribeFormValues>(
        key: K,
        value: SubscribeFormValues[K],
    ) => void;
    onAttributeChange?: (key: string, value: string) => void;
    onSubmit?: (event: FormEvent) => void;
    className?: string;
};

export function SubscribeFormView({
    form,
    preview = false,
    previewViewport = 'responsive',
    completed = false,
    successMessage,
    redirectCountdown,
    processing = false,
    errors = {},
    values,
    onChange,
    onAttributeChange,
    onSubmit,
    className,
}: Props) {
    const themeAttributes = {
        'data-subscribe-form-theme': '',
        'data-team-brand-color': form.theme.color,
        'data-team-brand-font': form.theme.font,
        'data-team-input-style': form.theme.inputStyle,
        style: subscribeFormThemeStyle(form.theme),
    };
    const reducedMotion = Boolean(useReducedMotion());
    const intro = {
        form,
        completed,
        successMessage,
        redirectCountdown,
        reducedMotion,
    };
    const fields = (
        <div className="relative" data-test="subscribe-form-morph">
            <SubscribeFormFields
                form={form}
                preview={preview}
                processing={processing}
                errors={errors}
                values={values}
                onChange={onChange}
                onAttributeChange={onAttributeChange}
            />
        </div>
    );

    const heading = <FormIntro {...intro} as="heading" />;
    const poweredByPosition = form.powered_by_form_position;
    const poweredByAbove = poweredByPosition.startsWith('top-');
    const submitContent =
        onSubmit && !preview ? (
            <form onSubmit={onSubmit}>{fields}</form>
        ) : (
            fields
        );
    const inlinePoweredBy = (
        <PoweredByMaildun
            enabled={!completed}
            position={poweredByPosition}
            preview={preview}
        />
    );
    const formContent = (
        <div className="flex flex-col gap-4">
            {poweredByAbove ? inlinePoweredBy : null}
            {submitContent}
            {poweredByAbove ? null : inlinePoweredBy}
        </div>
    );

    if (form.style === 'split' && previewViewport === 'mobile') {
        return (
            <div
                {...themeAttributes}
                className={cn(
                    'flex min-h-full items-center justify-center bg-background p-6',
                    className,
                )}
            >
                <div
                    className={cn(
                        'flex w-full max-w-sm flex-col',
                        stageSpacingClasses(
                            form.header_spacing,
                            completed,
                            reducedMotion,
                        ),
                    )}
                >
                    {heading}
                    <CollapsingFields
                        open={!completed}
                        reducedMotion={reducedMotion}
                    >
                        {formContent}
                    </CollapsingFields>
                </div>
            </div>
        );
    }

    if (form.style === 'split') {
        const imageFirst = form.image_side === 'left';

        return (
            <div
                {...themeAttributes}
                className={cn('grid min-h-full lg:grid-cols-2', className)}
            >
                {imageFirst ? (
                    <FormArtPanel form={form} preview={preview} fixed />
                ) : null}
                <div className="flex items-center justify-center bg-background p-8">
                    <div
                        className={cn(
                            'flex w-full max-w-sm flex-col',
                            stageSpacingClasses(
                                form.header_spacing,
                                completed,
                                reducedMotion,
                            ),
                        )}
                    >
                        {heading}
                        <CollapsingFields
                            open={!completed}
                            reducedMotion={reducedMotion}
                        >
                            {formContent}
                        </CollapsingFields>
                    </div>
                </div>
                {imageFirst ? null : (
                    <FormArtPanel form={form} preview={preview} fixed />
                )}
            </div>
        );
    }

    if (form.style === 'minimal') {
        return (
            <div
                {...themeAttributes}
                className={cn(
                    'flex min-h-full items-center justify-center bg-background p-6',
                    className,
                )}
            >
                <div
                    className={cn(
                        'flex w-full max-w-sm flex-col',
                        stageSpacingClasses(
                            form.header_spacing,
                            completed,
                            reducedMotion,
                        ),
                    )}
                >
                    <FormIntro {...intro} as="heading" />
                    <CollapsingFields
                        open={!completed}
                        reducedMotion={reducedMotion}
                    >
                        {formContent}
                    </CollapsingFields>
                </div>
            </div>
        );
    }

    if (form.style === 'cover') {
        return (
            <div
                {...themeAttributes}
                className={cn(
                    'relative grid min-h-full grid-cols-1 items-start',
                    className,
                )}
            >
                <FormArtPanel
                    form={form}
                    always
                    preview={preview}
                    fixed
                    className="pointer-events-none sticky top-0 z-0 col-start-1 row-start-1 w-full"
                />
                <div className="relative z-10 col-start-1 row-start-1 flex min-h-full items-center justify-center p-6">
                    <div className="flex w-full max-w-md flex-col">
                        {poweredByAbove ? (
                            <PoweredByMaildun
                                enabled={!completed}
                                position={poweredByPosition}
                                preview={preview}
                                outsideCard
                                variant="cover"
                            />
                        ) : null}
                        <Card
                            className={cn(
                                'w-full',
                                stageSpacingClasses(
                                    form.header_spacing,
                                    completed,
                                    reducedMotion,
                                ),
                                cardPaddingClasses[form.card_padding],
                            )}
                        >
                            <FormIntro {...intro} as="card" />
                            <CollapsingFields
                                open={!completed}
                                reducedMotion={reducedMotion}
                            >
                                <CardContent>{submitContent}</CardContent>
                            </CollapsingFields>
                        </Card>
                        {poweredByAbove ? null : (
                            <PoweredByMaildun
                                enabled={!completed}
                                position={poweredByPosition}
                                preview={preview}
                                outsideCard
                                variant="cover"
                            />
                        )}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div
            {...themeAttributes}
            className={cn(
                'flex min-h-full items-center justify-center bg-muted p-6',
                className,
            )}
        >
            <div className="flex w-full max-w-md flex-col">
                {poweredByAbove ? (
                    <PoweredByMaildun
                        enabled={!completed}
                        position={poweredByPosition}
                        preview={preview}
                        outsideCard
                    />
                ) : null}
                <Card
                    className={cn(
                        'w-full',
                        stageSpacingClasses(
                            form.header_spacing,
                            completed,
                            reducedMotion,
                        ),
                        cardPaddingClasses[form.card_padding],
                    )}
                >
                    <FormIntro {...intro} as="card" />
                    <CollapsingFields
                        open={!completed}
                        reducedMotion={reducedMotion}
                    >
                        <CardContent>{submitContent}</CardContent>
                    </CollapsingFields>
                </Card>
                {poweredByAbove ? null : (
                    <PoweredByMaildun
                        enabled={!completed}
                        position={poweredByPosition}
                        preview={preview}
                        outsideCard
                    />
                )}
            </div>
        </div>
    );
}

function PoweredByMaildun({
    enabled,
    position,
    preview,
    outsideCard = false,
    variant = 'default',
}: {
    enabled: boolean;
    position: SubscribeFormPoweredByPosition;
    preview: boolean;
    outsideCard?: boolean;
    variant?: 'default' | 'cover';
}) {
    const sourceUrl =
        usePage().props.attribution?.sourceUrl ??
        'https://github.com/abduns/maildun';

    if (!enabled) {
        return null;
    }

    const content = (
        <>
            <span
                className={cn(
                    'flex size-6 items-center justify-center rounded-md shadow-xs transition-transform group-hover:scale-105',
                    variant === 'cover'
                        ? 'bg-white/15 text-white ring-1 ring-white/20'
                        : 'bg-foreground text-background',
                )}
            >
                <AppLogoIcon className="h-3.5 w-auto" />
            </span>
            <span
                className={cn(
                    variant === 'cover'
                        ? 'text-white/75'
                        : 'text-muted-foreground',
                )}
            >
                Powered by
            </span>
            <span
                className={cn(
                    'font-semibold',
                    variant === 'cover' ? 'text-white' : 'text-foreground',
                )}
            >
                Maildun
            </span>
        </>
    );
    const className = cn(
        'group inline-flex items-center gap-1.5 rounded-lg border py-1 pr-2.5 pl-1 text-[11px] font-medium backdrop-blur-xl transition-all hover:-translate-y-px hover:shadow-md',
        variant === 'cover'
            ? 'border-white/25 bg-white/10 text-white shadow-lg shadow-black/10 hover:border-white/35 hover:bg-white/15'
            : 'border-border/60 bg-background/90 shadow-sm hover:border-border',
    );

    return (
        <div
            className={cn(
                'flex',
                position.startsWith('top-')
                    ? outsideCard
                        ? 'mb-6'
                        : 'mb-2'
                    : outsideCard
                      ? 'mt-6'
                      : 'mt-2',
                poweredByAlignmentClasses[position],
            )}
            data-test="subscribe-form-powered-by"
        >
            {preview ? (
                <span className={className}>{content}</span>
            ) : (
                <a
                    href={sourceUrl}
                    target="_blank"
                    rel="noreferrer"
                    className={className}
                >
                    {content}
                </a>
            )}
        </div>
    );
}

const poweredByAlignmentClasses: Record<
    SubscribeFormPoweredByPosition,
    string
> = {
    'top-left': 'justify-start',
    'top-center': 'justify-center',
    'top-right': 'justify-end',
    'bottom-left': 'justify-start',
    'bottom-center': 'justify-center',
    'bottom-right': 'justify-end',
};

function stageSpacingClasses(
    spacing: SubscribeFormHeaderSpacing,
    completed: boolean,
    reducedMotion: boolean,
): string {
    return cn(
        completed ? 'gap-0' : headerSpacingClasses[spacing],
        !reducedMotion && 'transition-[gap] duration-200 ease-out',
    );
}

function CollapsingFields({
    open,
    reducedMotion,
    children,
}: {
    open: boolean;
    reducedMotion: boolean;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'grid',
                open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                !reducedMotion &&
                    'transition-[grid-template-rows] duration-200 ease-out',
            )}
        >
            <div className="overflow-hidden" inert={!open}>
                <motion.div
                    animate={{ opacity: open ? 1 : 0 }}
                    transition={
                        reducedMotion ? { duration: 0 } : STAGE_TRANSITION
                    }
                >
                    {children}
                </motion.div>
            </div>
        </div>
    );
}

function SuccessMark({ reducedMotion }: { reducedMotion: boolean }) {
    return (
        <div
            className="flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground"
            aria-hidden="true"
            data-test="subscribe-form-success"
        >
            <svg viewBox="0 0 24 24" fill="none" className="size-7">
                <motion.path
                    d="m5 12 4 4L19 6"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    initial={reducedMotion ? false : { pathLength: 0 }}
                    animate={{ pathLength: 1 }}
                    transition={{
                        duration: reducedMotion ? 0 : 0.35,
                        ease: 'easeOut',
                    }}
                />
            </svg>
        </div>
    );
}

function FormIntro({
    form,
    as,
    completed,
    successMessage,
    redirectCountdown,
    reducedMotion,
}: {
    form: SubscribeFormAppearance;
    as: 'heading' | 'card';
    completed: boolean;
    successMessage?: string;
    redirectCountdown?: number | null;
    reducedMotion: boolean;
}) {
    const title = completed
        ? form.success_heading
        : form.headline || 'Join our newsletter';
    const description = completed
        ? successMessage || 'Thanks for subscribing!'
        : form.description;

    const copy = (
        <motion.div
            key={completed ? 'success' : 'form'}
            initial={reducedMotion ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={reducedMotion ? undefined : { opacity: 0 }}
            transition={reducedMotion ? { duration: 0 } : STAGE_TRANSITION}
            className={cn(
                'flex w-full flex-col',
                completed ? 'gap-4' : 'gap-1',
                completed && successAlignmentClasses[form.text_alignment],
            )}
        >
            {completed ? <SuccessMark reducedMotion={reducedMotion} /> : null}
            {as === 'card' ? (
                <div className="flex flex-col gap-1">
                    <CardTitle>{title}</CardTitle>
                    {description ? (
                        <CardDescription>{description}</CardDescription>
                    ) : null}
                    {completed && redirectCountdown != null ? (
                        <p
                            className="text-xs text-muted-foreground"
                            aria-live="polite"
                        >
                            Redirecting in {redirectCountdown}{' '}
                            {redirectCountdown === 1 ? 'second' : 'seconds'}…
                        </p>
                    ) : null}
                </div>
            ) : (
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description ? (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                    {completed && redirectCountdown != null ? (
                        <p
                            className="text-xs text-muted-foreground"
                            aria-live="polite"
                        >
                            Redirecting in {redirectCountdown}{' '}
                            {redirectCountdown === 1 ? 'second' : 'seconds'}…
                        </p>
                    ) : null}
                </div>
            )}
            {completed ? (
                <div
                    className="sr-only"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    {`${form.success_heading} ${successMessage || 'Thanks for subscribing!'}`}
                </div>
            ) : null}
        </motion.div>
    );

    if (as === 'card') {
        return (
            <CardHeader
                className={cn(
                    'relative',
                    textAlignmentClasses[form.text_alignment],
                    form.logo && 'gap-3',
                )}
            >
                <FormLogo form={form} />
                <AnimatePresence initial={false} mode="popLayout">
                    {copy}
                </AnimatePresence>
            </CardHeader>
        );
    }

    return (
        <div
            className={cn(
                'relative flex flex-col gap-4',
                textAlignmentClasses[form.text_alignment],
            )}
        >
            <FormLogo form={form} />
            <AnimatePresence initial={false} mode="popLayout">
                {copy}
            </AnimatePresence>
        </div>
    );
}

const textAlignmentClasses: Record<SubscribeFormTextAlignment, string> = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

const successAlignmentClasses: Record<SubscribeFormTextAlignment, string> = {
    left: 'items-start text-left',
    center: 'items-center text-center',
    right: 'items-end text-right',
};

const headerSpacingClasses: Record<SubscribeFormHeaderSpacing, string> = {
    compact: 'gap-3',
    default: 'gap-6',
    relaxed: 'gap-10',
    spacious: 'gap-16',
};

const cardPaddingClasses: Record<SubscribeFormCardPadding, string> = {
    compact: '[--card-spacing:--spacing(3)]',
    default: '[--card-spacing:--spacing(4)]',
    spacious: '[--card-spacing:--spacing(6)]',
};

const fieldSpacingClasses: Record<SubscribeFormCardPadding, string> = {
    compact: 'gap-4',
    default: 'gap-5',
    spacious: 'gap-7',
};

const logoPositionClasses: Record<SubscribeFormLogoPosition, string> = {
    left: 'justify-start',
    center: 'justify-center',
    right: 'justify-end',
};

const tiledLogoSizeClasses: Record<SubscribeFormLogoSize, string> = {
    small: 'size-8',
    medium: 'size-12',
    large: 'size-16',
};

const originalLogoSizeClasses: Record<SubscribeFormLogoSize, string> = {
    small: 'h-6 max-w-28',
    medium: 'h-8 max-w-40',
    large: 'h-12 max-w-56',
};

const logoShapeClasses: Record<SubscribeFormLogoShape, string> = {
    default: 'w-auto object-contain',
    square: 'rounded-none object-cover',
    'rounded-lg': 'rounded-lg object-cover',
    'rounded-xl': 'rounded-xl object-cover',
    'rounded-full': 'rounded-full object-cover',
};

function FormLogo({ form }: { form: SubscribeFormAppearance }) {
    if (!form.logo) {
        return null;
    }

    const tiled = form.logo_shape !== 'default';

    return (
        <div className={cn('flex', logoPositionClasses[form.logo_position])}>
            <img
                src={form.logo}
                alt=""
                data-test="subscribe-form-logo"
                className={cn(
                    tiled && 'overflow-hidden',
                    tiled
                        ? tiledLogoSizeClasses[form.logo_size]
                        : originalLogoSizeClasses[form.logo_size],
                    logoShapeClasses[form.logo_shape],
                )}
            />
        </div>
    );
}

function FormArtPanel({
    form,
    always = false,
    preview = false,
    fixed = false,
    className,
}: {
    form: SubscribeFormAppearance;
    always?: boolean;
    preview?: boolean;
    fixed?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative min-h-64 overflow-hidden bg-zinc-900 text-white',
                !always && 'hidden lg:block',
                fixed &&
                    (preview
                        ? 'sticky top-0 h-[calc(100dvh-7rem)] self-start xl:h-[calc(100dvh-3.5rem)]'
                        : 'sticky top-0 h-svh self-start'),
                className,
            )}
        >
            {form.artwork_type !== 'upload' && form.artwork_preset ? (
                <SubscribeFormArtworkVisual
                    preset={form.artwork_preset}
                    theme={form.theme}
                />
            ) : form.image_url ? (
                <img
                    src={form.image_url}
                    alt=""
                    className="size-full object-cover"
                />
            ) : (
                <>
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-white/20" />
                    <div className="relative z-10 flex h-full min-h-64 flex-col justify-between p-8">
                        <AppLogoIcon mode="dark" className="size-8" />
                        <p className="max-w-xs text-sm text-white/70">
                            {form.headline || 'Join our newsletter'}
                        </p>
                    </div>
                </>
            )}
        </div>
    );
}

function SubscribeFormFields({
    form,
    preview,
    processing,
    errors,
    values,
    onChange,
    onAttributeChange,
}: {
    form: SubscribeFormAppearance;
    preview: boolean;
    processing: boolean;
    errors: Record<string, string | undefined>;
    values?: SubscribeFormValues;
    onChange?: <K extends keyof SubscribeFormValues>(
        key: K,
        value: SubscribeFormValues[K],
    ) => void;
    onAttributeChange?: (key: string, value: string) => void;
}) {
    const disabled = preview;

    return (
        <FieldGroup className={fieldSpacingClasses[form.card_padding]}>
            {form.first_name_mode !== 'hidden' && (
                <Field data-invalid={Boolean(errors.first_name)}>
                    <FieldLabel htmlFor="first_name">
                        First name
                        {form.first_name_mode === 'optional' && (
                            <Badge>Optional</Badge>
                        )}
                    </FieldLabel>
                    <Input
                        id="first_name"
                        value={values?.first_name ?? ''}
                        onChange={(event) =>
                            onChange?.('first_name', event.target.value)
                        }
                        placeholder="Jane"
                        disabled={disabled}
                        aria-invalid={Boolean(errors.first_name)}
                        autoComplete="given-name"
                    />
                    <FieldError>{errors.first_name}</FieldError>
                </Field>
            )}
            {form.last_name_mode !== 'hidden' && (
                <Field data-invalid={Boolean(errors.last_name)}>
                    <FieldLabel htmlFor="last_name">
                        Last name
                        {form.last_name_mode === 'optional' && (
                            <Badge>Optional</Badge>
                        )}
                    </FieldLabel>
                    <Input
                        id="last_name"
                        value={values?.last_name ?? ''}
                        onChange={(event) =>
                            onChange?.('last_name', event.target.value)
                        }
                        placeholder="Doe"
                        disabled={disabled}
                        aria-invalid={Boolean(errors.last_name)}
                        autoComplete="family-name"
                    />
                    <FieldError>{errors.last_name}</FieldError>
                </Field>
            )}
            {form.attributes.map((attribute) => (
                <Field
                    key={attribute.key}
                    data-invalid={Boolean(
                        errors[`attributes.${attribute.key}`],
                    )}
                >
                    <FieldLabel htmlFor={`attribute-${attribute.key}`}>
                        {attribute.name}
                        {!attribute.required && <Badge>Optional</Badge>}
                    </FieldLabel>
                    {attribute.type === 'date' ? (
                        <AttributeDatePicker
                            id={`attribute-${attribute.key}`}
                            value={values?.attributes[attribute.key] ?? ''}
                            theme={form.theme}
                            disabled={disabled}
                            required={!preview && attribute.required}
                            invalid={Boolean(
                                errors[`attributes.${attribute.key}`],
                            )}
                            onChange={(value) =>
                                onAttributeChange?.(attribute.key, value)
                            }
                        />
                    ) : (
                        <Input
                            id={`attribute-${attribute.key}`}
                            type={attribute.type}
                            value={values?.attributes[attribute.key] ?? ''}
                            onChange={(event) =>
                                onAttributeChange?.(
                                    attribute.key,
                                    event.target.value,
                                )
                            }
                            placeholder={attribute.name}
                            disabled={disabled}
                            required={!preview && attribute.required}
                            aria-invalid={Boolean(
                                errors[`attributes.${attribute.key}`],
                            )}
                        />
                    )}
                    <FieldError>
                        {errors[`attributes.${attribute.key}`]}
                    </FieldError>
                </Field>
            ))}
            <Field data-invalid={Boolean(errors.email)}>
                <FieldLabel htmlFor="email">Email</FieldLabel>
                <Input
                    id="email"
                    type="email"
                    value={values?.email ?? ''}
                    onChange={(event) =>
                        onChange?.('email', event.target.value)
                    }
                    placeholder="email@example.com"
                    disabled={disabled}
                    aria-invalid={Boolean(errors.email)}
                    autoComplete="email"
                    required={!preview}
                />
                <FieldError>{errors.email}</FieldError>
            </Field>
            <Field
                orientation="horizontal"
                data-invalid={Boolean(errors.consent)}
            >
                <Checkbox
                    id="consent"
                    checked={values?.consent ?? false}
                    onCheckedChange={(checked) =>
                        onChange?.('consent', checked === true)
                    }
                    disabled={disabled}
                    aria-invalid={Boolean(errors.consent)}
                />
                <div className="flex flex-col gap-1">
                    <FieldLabel htmlFor="consent" className="font-normal">
                        {form.consent_text}
                    </FieldLabel>
                    <FieldError>{errors.consent}</FieldError>
                </div>
            </Field>
            {!preview && onChange ? (
                <div className="absolute -left-[10000px]" aria-hidden="true">
                    <label htmlFor="website">Website</label>
                    <input
                        id="website"
                        value={values?.website ?? ''}
                        onChange={(event) =>
                            onChange('website', event.target.value)
                        }
                        tabIndex={-1}
                        autoComplete="off"
                    />
                </div>
            ) : null}
            {errors.form ? (
                <p
                    className="text-sm text-destructive"
                    role="alert"
                    data-test="subscribe-form-submit-error"
                >
                    {errors.form}
                </p>
            ) : null}
            <Button
                type={preview ? 'button' : 'submit'}
                disabled={disabled || processing}
            >
                {processing && <Spinner data-icon="inline-start" />}
                {form.button_label || 'Subscribe'}
            </Button>
        </FieldGroup>
    );
}

function AttributeDatePicker({
    id,
    value,
    theme,
    disabled,
    required,
    invalid,
    onChange,
}: {
    id: string;
    value: string;
    theme: TeamBrandTheme;
    disabled: boolean;
    required: boolean;
    invalid: boolean;
    onChange: (value: string) => void;
}) {
    const selected = value
        ? parse(value, ATTRIBUTE_DATE_FORMAT, new Date())
        : undefined;
    const selectedDate =
        selected && !Number.isNaN(selected.getTime()) ? selected : undefined;

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        data-subscribe-date-trigger
                        data-has-value={selectedDate ? '' : undefined}
                        aria-label="Pick a date"
                        aria-invalid={invalid}
                        aria-required={required}
                        className={cn(
                            'w-full justify-start font-normal',
                            !selectedDate && 'text-muted-foreground',
                        )}
                    />
                }
            >
                <HugeiconsIcon icon={Calendar02Icon} data-icon="inline-start" />
                {selectedDate ? format(selectedDate, 'PPP') : 'Pick a date'}
            </PopoverTrigger>
            <PopoverContent
                className="w-auto p-0"
                data-subscribe-form-theme=""
                data-team-brand-color={theme.color}
                data-team-brand-font={theme.font}
                data-team-input-style={theme.inputStyle}
                style={subscribeFormThemeStyle(theme)}
            >
                <Calendar
                    mode="single"
                    selected={selectedDate}
                    onSelect={(date) =>
                        onChange(
                            date ? format(date, ATTRIBUTE_DATE_FORMAT) : '',
                        )
                    }
                />
            </PopoverContent>
        </Popover>
    );
}
