import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/campaign_series';
import type {
    CampaignSeriesDetail,
    CampaignSeriesGoal,
    CampaignSeriesGoalOption,
    ConversionEventOption,
} from '@/types';

const NOT_TRACKED = 'not_tracked';
const CUSTOM_EVENT = 'custom';
const ATTRIBUTION_WINDOWS = [7, 14, 30, 60, 90];

type Props = {
    teamSlug: string;
    goals: CampaignSeriesGoalOption[];
    conversionEvents: ConversionEventOption[];
    series?: CampaignSeriesDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function CampaignSeriesDialog({
    teamSlug,
    goals,
    conversionEvents,
    series = null,
    open,
    onOpenChange,
}: Props) {
    const editing = series !== null;
    const form = useForm({
        name: series?.name ?? '',
        description: series?.description ?? '',
        goal: series?.goal ?? goals[0]?.value ?? 'generate_leads',
        objective: series?.objective ?? '',
        primary_cta_url: series?.primary_cta_url ?? '',
        conversion_event_key: series?.conversion_event_key ?? '',
        attribution_window_days: series?.attribution_window_days ?? 30,
        conversion_target: series?.conversion_target?.toString() ?? '',
        value_currency: series?.value_currency ?? '',
    });
    const [customEvent, setCustomEvent] = useState(
        form.data.conversion_event_key !== '' &&
            !conversionEvents.some(
                (event) => event.value === form.data.conversion_event_key,
            ),
    );
    const eventChoice = customEvent
        ? CUSTOM_EVENT
        : form.data.conversion_event_key || NOT_TRACKED;
    const eventItems = [
        { value: NOT_TRACKED, label: 'Not tracked' },
        ...conversionEvents.map(({ value, label }) => ({
            value,
            label: `${label} (${value})`,
        })),
        { value: CUSTOM_EVENT, label: 'Custom event' },
    ];
    const windowItems = [
        ...new Set([...ATTRIBUTION_WINDOWS, form.data.attribution_window_days]),
    ]
        .sort((a, b) => a - b)
        .map((days) => ({ value: days.toString(), label: `${days} days` }));
    const tracksConversions = eventChoice !== NOT_TRACKED;

    const chooseEvent = (value: string) => {
        setCustomEvent(value === CUSTOM_EVENT);
        form.setData(
            'conversion_event_key',
            value === NOT_TRACKED || value === CUSTOM_EVENT ? '' : value,
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        };

        if (series) {
            form.patch(update.url([teamSlug, series.uuid]), options);
        } else {
            form.post(store.url(teamSlug), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Edit campaign series'
                                : 'Create campaign series'}
                        </DialogTitle>
                        <DialogDescription>
                            Group related sales emails and compare their results
                            in one report.
                        </DialogDescription>
                    </DialogHeader>

                    <FieldGroup className="mt-4">
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="series-name">Name</FieldLabel>
                            <Input
                                id="series-name"
                                autoFocus
                                required
                                maxLength={255}
                                placeholder="Q4 demo booking sequence"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.goal)}>
                            <FieldLabel htmlFor="series-goal">
                                Sales goal
                            </FieldLabel>
                            <Select
                                items={goals}
                                value={form.data.goal}
                                onValueChange={(value) =>
                                    form.setData(
                                        'goal',
                                        value as CampaignSeriesGoal,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="series-goal"
                                    className="w-full"
                                    aria-invalid={Boolean(form.errors.goal)}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {goals.map((goal) => (
                                            <SelectItem
                                                key={goal.value}
                                                value={goal.value}
                                            >
                                                {goal.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError>{form.errors.goal}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.objective)}>
                            <FieldLabel htmlFor="series-objective">
                                Objective
                            </FieldLabel>
                            <Input
                                id="series-objective"
                                maxLength={255}
                                placeholder="Book 30 qualified product demos"
                                value={form.data.objective}
                                onChange={(event) =>
                                    form.setData(
                                        'objective',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.objective)}
                            />
                            <FieldDescription>
                                Optional target your team can use when judging
                                the result.
                            </FieldDescription>
                            <FieldError>{form.errors.objective}</FieldError>
                        </Field>

                        <Field
                            data-invalid={Boolean(form.errors.primary_cta_url)}
                        >
                            <FieldLabel htmlFor="series-cta">
                                Primary CTA URL
                            </FieldLabel>
                            <Input
                                id="series-cta"
                                type="url"
                                maxLength={2048}
                                placeholder="https://example.com/book-a-demo"
                                value={form.data.primary_cta_url}
                                onChange={(event) =>
                                    form.setData(
                                        'primary_cta_url',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    form.errors.primary_cta_url,
                                )}
                            />
                            <FieldDescription>
                                Exact tracked link used to total the series CTA
                                clicks.
                            </FieldDescription>
                            <FieldError>
                                {form.errors.primary_cta_url}
                            </FieldError>
                        </Field>

                        <FieldSet>
                            <FieldLegend>Conversion tracking</FieldLegend>
                            <FieldDescription>
                                The primary CTA measures intent. A conversion
                                event, sent to the conversions API by your
                                booking, billing, or CRM system, measures the
                                completed sales outcome.
                            </FieldDescription>
                            <FieldGroup className="gap-5">
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.conversion_event_key,
                                    )}
                                >
                                    <FieldLabel htmlFor="series-conversion-event">
                                        Conversion event
                                    </FieldLabel>
                                    <Select
                                        items={eventItems}
                                        value={eventChoice}
                                        onValueChange={(value) =>
                                            chooseEvent(String(value))
                                        }
                                    >
                                        <SelectTrigger
                                            id="series-conversion-event"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {eventItems.map((item) => (
                                                    <SelectItem
                                                        key={item.value}
                                                        value={item.value}
                                                    >
                                                        {item.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    {customEvent && (
                                        <Input
                                            id="series-custom-event"
                                            aria-label="Custom event key"
                                            maxLength={64}
                                            placeholder="demo_requested"
                                            value={
                                                form.data.conversion_event_key
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'conversion_event_key',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors
                                                    .conversion_event_key,
                                            )}
                                        />
                                    )}
                                    <FieldError>
                                        {form.errors.conversion_event_key}
                                    </FieldError>
                                </Field>

                                {tracksConversions && (
                                    <>
                                        <Field
                                            data-invalid={Boolean(
                                                form.errors
                                                    .attribution_window_days,
                                            )}
                                        >
                                            <FieldLabel htmlFor="series-attribution-window">
                                                Attribution window
                                            </FieldLabel>
                                            <Select
                                                items={windowItems}
                                                value={form.data.attribution_window_days.toString()}
                                                onValueChange={(value) =>
                                                    form.setData(
                                                        'attribution_window_days',
                                                        Number(value),
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="series-attribution-window"
                                                    className="w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {windowItems.map(
                                                            (item) => (
                                                                <SelectItem
                                                                    key={
                                                                        item.value
                                                                    }
                                                                    value={
                                                                        item.value
                                                                    }
                                                                >
                                                                    {item.label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldDescription>
                                                The latest tracked click within
                                                this window before a conversion
                                                gets the credit.
                                            </FieldDescription>
                                            <FieldError>
                                                {
                                                    form.errors
                                                        .attribution_window_days
                                                }
                                            </FieldError>
                                        </Field>

                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field
                                                data-invalid={Boolean(
                                                    form.errors
                                                        .conversion_target,
                                                )}
                                            >
                                                <FieldLabel htmlFor="series-conversion-target">
                                                    Conversion target
                                                </FieldLabel>
                                                <Input
                                                    id="series-conversion-target"
                                                    type="number"
                                                    min={1}
                                                    inputMode="numeric"
                                                    placeholder="30"
                                                    value={
                                                        form.data
                                                            .conversion_target
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'conversion_target',
                                                            event.target.value,
                                                        )
                                                    }
                                                    aria-invalid={Boolean(
                                                        form.errors
                                                            .conversion_target,
                                                    )}
                                                />
                                                <FieldError>
                                                    {
                                                        form.errors
                                                            .conversion_target
                                                    }
                                                </FieldError>
                                            </Field>
                                            <Field
                                                data-invalid={Boolean(
                                                    form.errors.value_currency,
                                                )}
                                            >
                                                <FieldLabel htmlFor="series-value-currency">
                                                    Default currency
                                                </FieldLabel>
                                                <Input
                                                    id="series-value-currency"
                                                    maxLength={3}
                                                    placeholder="USD"
                                                    className="uppercase"
                                                    value={
                                                        form.data.value_currency
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'value_currency',
                                                            event.target.value.toUpperCase(),
                                                        )
                                                    }
                                                    aria-invalid={Boolean(
                                                        form.errors
                                                            .value_currency,
                                                    )}
                                                />
                                                <FieldError>
                                                    {form.errors.value_currency}
                                                </FieldError>
                                            </Field>
                                        </div>
                                    </>
                                )}
                            </FieldGroup>
                        </FieldSet>

                        <Field data-invalid={Boolean(form.errors.description)}>
                            <FieldLabel htmlFor="series-description">
                                Notes
                            </FieldLabel>
                            <Textarea
                                id="series-description"
                                maxLength={2000}
                                placeholder="Audience, offer, timing, and any hypothesis the sales team wants to compare."
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.description)}
                            />
                            <FieldError>{form.errors.description}</FieldError>
                        </Field>
                    </FieldGroup>

                    <DialogFooter className="mt-6 gap-2">
                        <DialogClose
                            render={
                                <Button type="button" variant="secondary" />
                            }
                        >
                            Cancel
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            {editing ? 'Save series' : 'Create series'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
