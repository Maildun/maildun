import { FilterMailIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type FilterMenuOption = {
    value: string;
    label: string;
};

export type FilterMenuField = {
    key: string;
    icon: IconSvgElement;
    label: string;
    value: string;
    allValue?: string;
    allLabel: string;
    options: FilterMenuOption[];
    onValueChange: (value: string) => void;
    testId?: string;
};

function fieldNoneValue(field: FilterMenuField): string {
    return field.allValue ?? 'all';
}

function fieldIsActive(field: FilterMenuField): boolean {
    const none = field.allValue ?? '';

    return field.value !== '' && field.value !== none;
}

export function FilterMenu({
    fields,
    testId,
}: {
    fields: FilterMenuField[];
    testId?: string;
}) {
    const visibleFields = fields.filter(
        (field) => field.options.length > 0 || fieldIsActive(field),
    );
    const count = visibleFields.filter(fieldIsActive).length;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        type="button"
                        variant="outline"
                        data-test={testId}
                    />
                }
            >
                <HugeiconsIcon
                    icon={FilterMailIcon}
                    data-icon="inline-start"
                    className="size-3.5"
                />
                Filter
                {count > 0 ? (
                    <span className="grid size-5 place-items-center rounded-full bg-foreground text-[10px] font-medium text-background">
                        {count}
                    </span>
                ) : null}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuGroup>
                    {visibleFields.map((field) => {
                        const noneValue = fieldNoneValue(field);
                        const radioValue = fieldIsActive(field)
                            ? field.value
                            : noneValue;

                        return (
                            <DropdownMenuSub key={field.key}>
                                <DropdownMenuSubTrigger
                                    data-test={field.testId}
                                >
                                    <HugeiconsIcon
                                        icon={field.icon}
                                        className="size-3.5"
                                    />
                                    {field.label}
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent>
                                    <DropdownMenuRadioGroup
                                        value={radioValue}
                                        onValueChange={(value) =>
                                            field.onValueChange(
                                                value === noneValue
                                                    ? (field.allValue ?? '')
                                                    : value,
                                            )
                                        }
                                    >
                                        <DropdownMenuRadioItem
                                            value={noneValue}
                                        >
                                            {field.allLabel}
                                        </DropdownMenuRadioItem>
                                        {field.options.map((option) => (
                                            <DropdownMenuRadioItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </DropdownMenuRadioItem>
                                        ))}
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                        );
                    })}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
