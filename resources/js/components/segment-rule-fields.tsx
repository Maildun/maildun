import { Calendar02Icon, Delete02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { format, parse } from 'date-fns';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    SegmentRule,
    SegmentRuleField,
    SegmentRuleOperator,
    SegmentRuleOptions,
    SubscriberLanguageOption,
} from '@/types/audiences';

const RULE_DATE_FORMAT = 'yyyy-MM-dd';
const MAX_RULES = 10;
const RULE_SELECT_TRIGGER_CLASSNAME = 'w-full border-none bg-muted';

type SegmentRuleErrors = {
    field?: string;
    operator?: string;
    value?: string;
};

export type SegmentFormData = {
    name: string;
    description: string;
    match_type: 'all' | 'any';
    rules: SegmentRule[];
};

export function SegmentRuleFields({
    idPrefix = 'segment',
    data,
    setData,
    errors,
    options,
    languages,
    disabled = false,
}: {
    idPrefix?: string;
    data: SegmentFormData;
    setData: <K extends keyof SegmentFormData>(
        key: K,
        value: SegmentFormData[K],
    ) => void;
    errors?: Partial<Record<string, string>>;
    options: SegmentRuleOptions;
    languages: SubscriberLanguageOption[];
    disabled?: boolean;
}) {
    const canAddRule = data.rules.length < MAX_RULES;

    return (
        <FieldGroup>
            <Field data-invalid={Boolean(errors?.name)}>
                <FieldLabel htmlFor={`${idPrefix}-name`}>Name</FieldLabel>
                <Input
                    id={`${idPrefix}-name`}
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    disabled={disabled}
                    placeholder="Engaged subscribers"
                    aria-invalid={Boolean(errors?.name)}
                />
                <FieldError>{errors?.name}</FieldError>
            </Field>
            <Field data-invalid={Boolean(errors?.description)}>
                <FieldLabel htmlFor={`${idPrefix}-description`}>
                    Description
                </FieldLabel>
                <Textarea
                    id={`${idPrefix}-description`}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    disabled={disabled}
                    placeholder="Who this segment is for, and why"
                    aria-invalid={Boolean(errors?.description)}
                />
                <FieldError>{errors?.description}</FieldError>
            </Field>
            <Field data-invalid={Boolean(errors?.match_type)}>
                <FieldLabel>Match</FieldLabel>
                <Select
                    value={data.match_type}
                    onValueChange={(value) => {
                        if (value) {
                            setData('match_type', value);
                        }
                    }}
                    disabled={disabled}
                >
                    <SelectTrigger aria-invalid={Boolean(errors?.match_type)}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="all">
                                Match all rules (AND)
                            </SelectItem>
                            <SelectItem value="any">
                                Match any rule (OR)
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <FieldError>{errors?.match_type}</FieldError>
            </Field>
            <div className="flex flex-col gap-3">
                {data.rules.map((rule, index) => (
                    <SegmentRuleRow
                        key={index}
                        rule={rule}
                        errors={{
                            field: errors?.[`rules.${index}.field`],
                            operator: errors?.[`rules.${index}.operator`],
                            value: errors?.[`rules.${index}.value`],
                        }}
                        options={options}
                        languages={languages}
                        disabled={disabled}
                        canRemove={data.rules.length > 1}
                        onChange={(nextRule) =>
                            setData(
                                'rules',
                                data.rules.map((item, ruleIndex) =>
                                    ruleIndex === index ? nextRule : item,
                                ),
                            )
                        }
                        onRemove={() =>
                            setData(
                                'rules',
                                data.rules.filter(
                                    (_, ruleIndex) => ruleIndex !== index,
                                ),
                            )
                        }
                    />
                ))}
                <FieldError>{errors?.rules}</FieldError>
                {!disabled && (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!canAddRule}
                        onClick={() =>
                            setData('rules', [
                                ...data.rules,
                                {
                                    field: 'email',
                                    operator: 'contains',
                                    value: '',
                                },
                            ])
                        }
                    >
                        {canAddRule ? 'Add rule' : `Up to ${MAX_RULES} rules`}
                    </Button>
                )}
            </div>
        </FieldGroup>
    );
}

function SegmentRuleRow({
    rule,
    errors,
    options,
    languages,
    disabled,
    canRemove,
    onChange,
    onRemove,
}: {
    rule: SegmentRule;
    errors: SegmentRuleErrors;
    options: SegmentRuleOptions;
    languages: SubscriberLanguageOption[];
    disabled: boolean;
    canRemove: boolean;
    onChange: (rule: SegmentRule) => void;
    onRemove: () => void;
}) {
    const operators = useMemo(() => operatorsFor(rule.field), [rule.field]);
    const ruleErrors = [errors.field, errors.operator, errors.value]
        .filter((message): message is string => Boolean(message))
        .map((message) => ({ message }));

    return (
        <Field data-invalid={ruleErrors.length > 0} className="gap-2">
            <div className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_1fr_1fr_auto]">
                <Select
                    value={rule.field}
                    disabled={disabled}
                    onValueChange={(value) => {
                        if (!value) {
                            return;
                        }

                        onChange({
                            field: value,
                            operator: operatorsFor(value)[0].value,
                            value: defaultRuleValue(value, options, languages),
                        });
                    }}
                >
                    <SelectTrigger
                        aria-label="Rule field"
                        aria-invalid={Boolean(errors.field)}
                        className={RULE_SELECT_TRIGGER_CLASSNAME}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="email">Email</SelectItem>
                            <SelectItem value="first_name">
                                First name
                            </SelectItem>
                            <SelectItem value="last_name">Last name</SelectItem>
                            <SelectItem value="status">Status</SelectItem>
                            <SelectItem value="source">Source</SelectItem>
                            <SelectItem value="subscribe_form">
                                Subscribe form
                            </SelectItem>
                            <SelectItem value="subscribed_at">
                                Subscribed date
                            </SelectItem>
                            <SelectItem value="language">Language</SelectItem>
                            <SelectItem value="tag">Tag</SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <Select
                    value={rule.operator}
                    disabled={disabled}
                    onValueChange={(value) => {
                        if (value) {
                            onChange({ ...rule, operator: value });
                        }
                    }}
                >
                    <SelectTrigger
                        aria-label="Rule operator"
                        aria-invalid={Boolean(errors.operator)}
                        className={RULE_SELECT_TRIGGER_CLASSNAME}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            {operators.map((operator) => (
                                <SelectItem
                                    key={operator.value}
                                    value={operator.value}
                                >
                                    {operator.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <RuleValue
                    rule={rule}
                    invalid={Boolean(errors.value)}
                    options={options}
                    languages={languages}
                    disabled={disabled}
                    onChange={(value) => onChange({ ...rule, value })}
                />
                {!disabled && (
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        onClick={onRemove}
                        disabled={!canRemove}
                        aria-label="Remove rule"
                    >
                        <HugeiconsIcon icon={Delete02Icon} />
                    </Button>
                )}
            </div>
            <FieldError errors={ruleErrors} />
        </Field>
    );
}

function RuleValue({
    rule,
    invalid,
    options,
    languages,
    disabled,
    onChange,
}: {
    rule: SegmentRule;
    invalid: boolean;
    options: SegmentRuleOptions;
    languages: SubscriberLanguageOption[];
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    if (rule.field === 'status') {
        return (
            <RuleSelect
                value={rule.value}
                invalid={invalid}
                disabled={disabled}
                onChange={onChange}
                options={[
                    ['subscribed', 'Subscribed'],
                    ['unsubscribed', 'Unsubscribed'],
                ]}
            />
        );
    }

    if (rule.field === 'source') {
        return (
            <RuleSelect
                value={rule.value}
                invalid={invalid}
                disabled={disabled}
                onChange={onChange}
                options={options.sources.map((source) => [
                    source.value,
                    source.label,
                ])}
            />
        );
    }

    if (rule.field === 'subscribe_form') {
        return (
            <RuleSelect
                value={rule.value}
                invalid={invalid}
                disabled={disabled}
                onChange={onChange}
                options={options.forms.map((form) => [
                    form.uuid,
                    form.deleted ? `${form.name} (deleted)` : form.name,
                ])}
                placeholder={
                    options.forms.length > 0
                        ? 'Select value'
                        : 'No subscribe forms in this audience'
                }
            />
        );
    }

    if (rule.field === 'language') {
        return (
            <RuleSelect
                value={rule.value}
                invalid={invalid}
                disabled={disabled}
                onChange={onChange}
                options={languages.map((language) => [
                    language.value,
                    language.label,
                ])}
            />
        );
    }

    if (rule.field === 'tag') {
        return (
            <RuleSelect
                value={rule.value}
                invalid={invalid}
                disabled={disabled}
                onChange={onChange}
                options={options.tags.map((tag) => [tag.uuid, tag.name])}
                placeholder={
                    options.tags.length > 0
                        ? 'Select value'
                        : 'No tags in this workspace'
                }
            />
        );
    }

    if (rule.field === 'subscribed_at') {
        return (
            <RuleDatePicker
                value={rule.value}
                invalid={invalid}
                disabled={disabled}
                onChange={onChange}
            />
        );
    }

    return (
        <Input
            type="text"
            value={rule.value}
            disabled={disabled}
            onChange={(event) => onChange(event.target.value)}
            placeholder="Value to match"
            aria-label="Rule value"
            aria-invalid={invalid}
        />
    );
}

function RuleDatePicker({
    value,
    invalid,
    disabled,
    onChange,
}: {
    value: string;
    invalid: boolean;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    const selected = value
        ? parse(value, RULE_DATE_FORMAT, new Date())
        : undefined;
    const isValidSelected = selected && !Number.isNaN(selected.getTime());

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        aria-label="Rule value"
                        aria-invalid={invalid}
                        className={cn(
                            'w-full justify-start font-normal',
                            !isValidSelected && 'text-muted-foreground',
                        )}
                    />
                }
            >
                <HugeiconsIcon icon={Calendar02Icon} data-icon="inline-start" />
                {isValidSelected
                    ? `${format(selected, 'PPP')} (UTC)`
                    : 'Pick a date (UTC)'}
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
                <Calendar
                    mode="single"
                    selected={isValidSelected ? selected : undefined}
                    onSelect={(date) =>
                        onChange(date ? format(date, RULE_DATE_FORMAT) : '')
                    }
                />
            </PopoverContent>
        </Popover>
    );
}

function RuleSelect({
    value,
    invalid,
    disabled,
    onChange,
    options,
    placeholder = 'Select value',
}: {
    value: string;
    invalid: boolean;
    disabled: boolean;
    onChange: (value: string) => void;
    options: string[][];
    placeholder?: string;
}) {
    return (
        <Select
            value={value}
            onValueChange={(next) => {
                if (next) {
                    onChange(next);
                }
            }}
            disabled={disabled}
        >
            <SelectTrigger
                aria-label="Rule value"
                aria-invalid={invalid}
                className={RULE_SELECT_TRIGGER_CLASSNAME}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {options.map(([optionValue, label]) => (
                        <SelectItem key={optionValue} value={optionValue}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}

function operatorsFor(
    field: SegmentRuleField,
): { value: SegmentRuleOperator; label: string }[] {
    if (field === 'subscribed_at') {
        return [
            { value: 'before', label: 'Before' },
            { value: 'after', label: 'After' },
        ];
    }

    if (field === 'tag') {
        return [
            { value: 'equals', label: 'Has' },
            { value: 'not_equals', label: 'Does not have' },
        ];
    }

    if (['status', 'source', 'subscribe_form', 'language'].includes(field)) {
        return [
            { value: 'equals', label: 'Is' },
            { value: 'not_equals', label: 'Is not' },
        ];
    }

    return [
        { value: 'equals', label: 'Equals' },
        { value: 'not_equals', label: 'Does not equal' },
        { value: 'contains', label: 'Contains' },
        { value: 'does_not_contain', label: 'Does not contain' },
    ];
}

function defaultRuleValue(
    field: SegmentRuleField,
    options: SegmentRuleOptions,
    languages: SubscriberLanguageOption[],
): string {
    if (field === 'status') {
        return 'subscribed';
    }

    if (field === 'source') {
        return 'manual';
    }

    if (field === 'subscribe_form') {
        return options.forms.find((form) => !form.deleted)?.uuid || '';
    }

    if (field === 'language') {
        return languages[0]?.value || '';
    }

    if (field === 'tag') {
        return options.tags[0]?.uuid || '';
    }

    return '';
}
