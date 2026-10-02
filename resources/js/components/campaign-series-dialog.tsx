import { useForm } from '@inertiajs/react';
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
} from '@/types';

type Props = {
    teamSlug: string;
    goals: CampaignSeriesGoalOption[];
    series?: CampaignSeriesDetail | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function CampaignSeriesDialog({
    teamSlug,
    goals,
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
    });

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
