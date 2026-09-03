/**
 * Renders the attribution notice required by the additional terms in LICENSE,
 * added under section 7(b) of the GNU Affero General Public License. Removing
 * or hiding it terminates the rights granted by that license.
 */
import { Calendar02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { format, parse } from 'date-fns';
import type { FormEvent } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { AttributionBadge } from '@/components/attribution-badge';
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
    SubscribeFormFieldMode,
    SubscribeFormImageSide,
    SubscribeFormLogoShape,
    SubscribeFormLogoSize,
    SubscribeFormStyle,
    SubscribeFormTextAlignment,
} from '@/types/audiences';

const ATTRIBUTE_DATE_FORMAT = 'yyyy-MM-dd';

export type SubscribeFormAppearance = {
    theme: TeamBrandTheme;
    headline: string;
    description: string | null;
    text_alignment: SubscribeFormTextAlignment;
    button_label: string;
    success_heading: string;
    consent_text: string;
    style: SubscribeFormStyle;
    image_side: SubscribeFormImageSide;
    image_url: string | null;
    logo: string | null;
    logo_shape: SubscribeFormLogoShape;
    logo_size: SubscribeFormLogoSize;
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
    completed?: boolean;
    successMessage?: string;
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
    completed = false,
    successMessage,
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
    const fields = completed ? (
        <div
            className={cn(
                'rounded-lg bg-muted p-4 motion-safe:animate-in motion-safe:duration-300 motion-safe:fade-in-0 motion-safe:zoom-in-95',
                textAlignmentClasses[form.text_alignment],
            )}
        >
            <p className="font-medium">{form.success_heading}</p>
            <p className="mt-1 text-sm text-muted-foreground">
                {successMessage || 'Thanks for subscribing!'}
            </p>
        </div>
    ) : (
        <SubscribeFormFields
            form={form}
            preview={preview}
            processing={processing}
            errors={errors}
            values={values}
            onChange={onChange}
            onAttributeChange={onAttributeChange}
        />
    );

    const heading = <FormIntro form={form} logoAlign="start" as="heading" />;

    if (form.style === 'split') {
        const imageFirst = form.image_side === 'left';

        return (
            <div
                {...themeAttributes}
                className={cn('grid min-h-full lg:grid-cols-2', className)}
            >
                {imageFirst ? <FormArtPanel form={form} /> : null}
                <div className="flex items-center justify-center bg-background p-8">
                    <div className="flex w-full max-w-sm flex-col gap-6">
                        {heading}
                        {onSubmit && !preview ? (
                            <form onSubmit={onSubmit}>{fields}</form>
                        ) : (
                            fields
                        )}
                    </div>
                </div>
                {imageFirst ? null : <FormArtPanel form={form} />}
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
                <div className="flex w-full max-w-sm flex-col gap-6">
                    <FormIntro form={form} logoAlign="center" as="heading" />
                    {onSubmit && !preview ? (
                        <form onSubmit={onSubmit}>{fields}</form>
                    ) : (
                        fields
                    )}
                </div>
            </div>
        );
    }

    if (form.style === 'cover') {
        return (
            <div
                {...themeAttributes}
                className={cn(
                    'relative flex min-h-full items-center justify-center overflow-hidden p-6',
                    className,
                )}
            >
                <FormArtPanel
                    form={form}
                    always
                    className="pointer-events-none absolute inset-0 z-0 min-h-full"
                />
                <Card className="relative z-10 w-full max-w-md">
                    <FormIntro form={form} logoAlign="center" as="card" />
                    <CardContent>
                        {onSubmit && !preview ? (
                            <form onSubmit={onSubmit}>{fields}</form>
                        ) : (
                            fields
                        )}
                    </CardContent>
                </Card>
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
            <Card className="w-full max-w-md">
                <FormIntro form={form} logoAlign="center" as="card" />
                <CardContent>
                    {onSubmit && !preview ? (
                        <form onSubmit={onSubmit}>{fields}</form>
                    ) : (
                        fields
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function FormIntro({
    form,
    logoAlign,
    as,
}: {
    form: SubscribeFormAppearance;
    logoAlign: 'start' | 'center';
    as: 'heading' | 'card';
}) {
    const headline = form.headline || 'Join our newsletter';

    if (as === 'card') {
        return (
            <CardHeader
                className={cn(
                    textAlignmentClasses[form.text_alignment],
                    form.logo && 'gap-3',
                )}
            >
                <FormLogo form={form} align={logoAlign} />
                <CardTitle>{headline}</CardTitle>
                {form.description ? (
                    <CardDescription>{form.description}</CardDescription>
                ) : null}
            </CardHeader>
        );
    }

    return (
        <div
            className={cn(
                'flex flex-col gap-4',
                textAlignmentClasses[form.text_alignment],
            )}
        >
            <FormLogo form={form} align={logoAlign} />
            <div className="flex flex-col gap-1">
                <h1 className="text-xl font-semibold tracking-tight">
                    {headline}
                </h1>
                {form.description ? (
                    <p className="text-sm text-muted-foreground">
                        {form.description}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

const textAlignmentClasses: Record<SubscribeFormTextAlignment, string> = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
};

const logoSizeClasses: Record<
    SubscribeFormLogoShape,
    Record<SubscribeFormLogoSize, string>
> = {
    square: {
        small: 'size-8',
        medium: 'size-12',
        large: 'size-16',
    },
    default: {
        small: 'h-6 max-w-28',
        medium: 'h-8 max-w-40',
        large: 'h-12 max-w-56',
    },
};

function FormLogo({
    form,
    align,
}: {
    form: SubscribeFormAppearance;
    align: 'start' | 'center';
}) {
    if (!form.logo) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex',
                align === 'center' ? 'justify-center' : 'justify-start',
            )}
        >
            <img
                src={form.logo}
                alt=""
                data-test="subscribe-form-logo"
                className={cn(
                    logoSizeClasses[form.logo_shape][form.logo_size],
                    form.logo_shape === 'square'
                        ? 'rounded-md object-cover'
                        : 'w-auto object-contain',
                )}
            />
        </div>
    );
}

function FormArtPanel({
    form,
    always = false,
    className,
}: {
    form: SubscribeFormAppearance;
    always?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative min-h-64 overflow-hidden bg-zinc-900 text-white',
                !always && 'hidden lg:block',
                className,
            )}
        >
            {form.image_url ? (
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
        <FieldGroup>
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
            <Button
                type={preview ? 'button' : 'submit'}
                disabled={disabled || processing}
            >
                {processing && <Spinner data-icon="inline-start" />}
                {form.button_label || 'Subscribe'}
            </Button>
            <AttributionBadge />
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
