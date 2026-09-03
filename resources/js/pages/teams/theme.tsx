import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
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
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { useAppearance } from '@/hooks/use-appearance';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import {
    subscribeFormThemeStyle,
    teamBrandFonts,
    teamBrandPalettes,
} from '@/lib/team-brand-theme';
import { update } from '@/routes/teams/theme';
import type {
    Team,
    TeamBrandColor,
    TeamBrandFont,
    TeamBrandInputStyle,
    TeamPermissions,
} from '@/types';

type Option = { value: string; label: string };

type Props = {
    team: Team;
    permissions: TeamPermissions;
    colors: Option[];
    fonts: Option[];
    inputStyles: Option[];
};

export default function TeamTheme({
    team,
    permissions,
    colors,
    fonts,
    inputStyles,
}: Props) {
    const [color, setColor] = useState<TeamBrandColor>(team.brandTheme.color);
    const [font, setFont] = useState<TeamBrandFont>(team.brandTheme.font);
    const [inputStyle, setInputStyle] = useState<TeamBrandInputStyle>(
        team.brandTheme.inputStyle,
    );
    const canManage = permissions.canUpdateTeam;
    const { resolvedAppearance } = useAppearance();
    const previewPalette = teamBrandPalettes[color];
    const previewPrimary = previewPalette[resolvedAppearance];
    const previewTheme = { color, font, inputStyle };

    return (
        <>
            <Head title={`Form theme defaults · ${team.name}`} />
            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="Form theme defaults" />
                <Form
                    {...update.form.patch(team.slug)}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                    onSuccess={() =>
                        toast.add({
                            type: 'success',
                            title: 'Changes saved.',
                        })
                    }
                    onError={() => focusFirstInvalidField()}
                >
                    {({ errors, processing, isDirty }) => {
                        const hasThemeChanges =
                            color !== team.brandTheme.color ||
                            font !== team.brandTheme.font ||
                            inputStyle !== team.brandTheme.inputStyle;
                        const formIsDirty = isDirty || hasThemeChanges;

                        return (
                            <div className="flex flex-col gap-6">
                                <UnsavedChangesGuard isDirty={formIsDirty} />
                                <input
                                    type="hidden"
                                    name="brand_color"
                                    value={color}
                                />
                                <input
                                    type="hidden"
                                    name="brand_font"
                                    value={font}
                                />
                                <input
                                    type="hidden"
                                    name="brand_input_style"
                                    value={inputStyle}
                                />

                                <SettingsPanel
                                    variant="inset"
                                    title="Default form theme"
                                    description="Choose the starting theme for new subscribe forms. Existing forms keep their own settings."
                                >
                                    <FieldGroup className="p-6 sm:p-7">
                                        <Field
                                            data-invalid={Boolean(
                                                errors.brand_color,
                                            )}
                                        >
                                            <FieldLabel htmlFor="brand-color">
                                                Button color
                                            </FieldLabel>
                                            <Select
                                                items={colors}
                                                value={color}
                                                disabled={!canManage}
                                                onValueChange={(value) => {
                                                    if (value !== null) {
                                                        setColor(
                                                            value as TeamBrandColor,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="brand-color"
                                                    className="w-full"
                                                    aria-invalid={Boolean(
                                                        errors.brand_color,
                                                    )}
                                                >
                                                    <span className="flex items-center gap-2">
                                                        <span
                                                            aria-hidden="true"
                                                            className="size-3 rounded-full border border-black/10 dark:border-white/10"
                                                            style={{
                                                                backgroundColor:
                                                                    previewPrimary,
                                                            }}
                                                        />
                                                        <SelectValue />
                                                    </span>
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {colors.map(
                                                            (option) => {
                                                                const palette =
                                                                    teamBrandPalettes[
                                                                        option.value as TeamBrandColor
                                                                    ];

                                                                return (
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
                                                                                    palette[
                                                                                        resolvedAppearance
                                                                                    ],
                                                                            }}
                                                                        />
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                );
                                                            },
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldDescription>
                                                Used for subscribe buttons and
                                                form focus states.
                                            </FieldDescription>
                                            <FieldError>
                                                {errors.brand_color}
                                            </FieldError>
                                        </Field>

                                        <Field
                                            data-invalid={Boolean(
                                                errors.brand_font,
                                            )}
                                        >
                                            <FieldLabel htmlFor="brand-font">
                                                Font
                                            </FieldLabel>
                                            <Select
                                                items={fonts}
                                                value={font}
                                                disabled={!canManage}
                                                onValueChange={(value) => {
                                                    if (value !== null) {
                                                        setFont(
                                                            value as TeamBrandFont,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="brand-font"
                                                    className="w-full"
                                                    aria-invalid={Boolean(
                                                        errors.brand_font,
                                                    )}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {fonts.map((option) => (
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
                                                                {option.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError>
                                                {errors.brand_font}
                                            </FieldError>
                                        </Field>

                                        <Field
                                            data-invalid={Boolean(
                                                errors.brand_input_style,
                                            )}
                                        >
                                            <FieldLabel htmlFor="brand-input-style">
                                                Input fields
                                            </FieldLabel>
                                            <Select
                                                items={inputStyles}
                                                value={inputStyle}
                                                disabled={!canManage}
                                                onValueChange={(value) => {
                                                    if (value !== null) {
                                                        setInputStyle(
                                                            value as TeamBrandInputStyle,
                                                        );
                                                    }
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="brand-input-style"
                                                    className="w-full"
                                                    aria-invalid={Boolean(
                                                        errors.brand_input_style,
                                                    )}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {inputStyles.map(
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
                                                {errors.brand_input_style}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>

                                    {canManage ? (
                                        <div className="flex items-center justify-end border-t border-border px-6 py-5 sm:px-7">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing || !formIsDirty
                                                }
                                                data-test="save-team-theme"
                                                className="w-full sm:w-auto"
                                            >
                                                {processing && (
                                                    <Spinner data-icon="inline-start" />
                                                )}
                                                Save changes
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="border-t border-border px-6 py-5 text-sm text-muted-foreground sm:px-7">
                                            Only workspace owners and admins can
                                            change these settings.
                                        </p>
                                    )}
                                </SettingsPanel>
                            </div>
                        );
                    }}
                </Form>

                <SettingsPanel
                    variant="inset"
                    title="Preview"
                    description="Preview the theme that new subscribe forms will start with."
                >
                    <div className="p-6 sm:p-7">
                        <div
                            data-subscribe-form-theme
                            data-team-brand-color={color}
                            data-team-input-style={inputStyle}
                            data-team-brand-font={font}
                            style={subscribeFormThemeStyle(previewTheme)}
                            className="flex flex-col gap-6"
                        >
                            <div className="flex flex-col gap-1 px-6">
                                <h3 className="font-heading text-base leading-snug font-medium">
                                    Join {team.name}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Get product news and useful updates in your
                                    inbox.
                                </p>
                            </div>
                            <div className="flex flex-col gap-4 px-6">
                                <Field>
                                    <FieldLabel htmlFor="theme-preview-email">
                                        Email address
                                    </FieldLabel>
                                    <Input
                                        id="theme-preview-email"
                                        placeholder="hello@example.com"
                                        defaultValue="hello@example.com"
                                    />
                                </Field>
                                <div className="flex flex-wrap gap-2">
                                    <Button type="button">
                                        Primary action
                                    </Button>
                                    <Button type="button" variant="secondary">
                                        Secondary action
                                    </Button>
                                </div>
                            </div>
                            <div className="border-t px-6 py-4">
                                <p className="text-xs text-muted-foreground">
                                    Preview only — no data is submitted.
                                </p>
                            </div>
                        </div>
                    </div>
                </SettingsPanel>
            </div>
        </>
    );
}
