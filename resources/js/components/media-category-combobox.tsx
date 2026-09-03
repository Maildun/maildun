import { useMemo, useState } from 'react';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';

const CREATE_PREFIX = '__create__:';

type MediaCategoryComboboxProps = {
    id?: string;
    value: string;
    onValueChange: (name: string) => void;
    availableCategories: { name: string }[];
    placeholder?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
};

export function MediaCategoryCombobox({
    id,
    value,
    onValueChange,
    availableCategories,
    placeholder = 'Search or create a category…',
    disabled,
    'aria-invalid': ariaInvalid,
}: MediaCategoryComboboxProps) {
    const [inputValue, setInputValue] = useState(value);

    const trimmedInput = inputValue.trim();
    const lowerInput = trimmedInput.toLowerCase();

    const matches = useMemo(
        () =>
            availableCategories
                .filter(
                    (category) =>
                        !trimmedInput ||
                        category.name.toLowerCase().includes(lowerInput),
                )
                .map((category) => category.name),
        [availableCategories, trimmedInput, lowerInput],
    );

    const canCreate =
        trimmedInput.length > 0 &&
        trimmedInput.toLowerCase() !== value.toLowerCase() &&
        !availableCategories.some(
            (category) => category.name.toLowerCase() === lowerInput,
        );

    const items = useMemo(
        () =>
            canCreate
                ? [...matches, `${CREATE_PREFIX}${trimmedInput}`]
                : matches,
        [matches, canCreate, trimmedInput],
    );

    const handleValueChange = (next: string | null) => {
        if (!next) {
            onValueChange('');
            setInputValue('');

            return;
        }

        const name = (
            next.startsWith(CREATE_PREFIX)
                ? next.slice(CREATE_PREFIX.length)
                : next
        ).trim();

        onValueChange(name);
        setInputValue(name);
    };

    return (
        <Combobox
            autoHighlight
            items={items}
            filter={null}
            value={value || null}
            onValueChange={handleValueChange}
            inputValue={inputValue}
            onInputValueChange={setInputValue}
            disabled={disabled}
        >
            <ComboboxInput
                id={id}
                placeholder={placeholder}
                className="w-full"
                showClear={value !== ''}
                disabled={disabled}
                aria-invalid={ariaInvalid}
                data-test="media-category-combobox"
            />
            <ComboboxContent>
                <ComboboxEmpty>No categories found.</ComboboxEmpty>
                <ComboboxList>
                    {(item: string) =>
                        item.startsWith(CREATE_PREFIX) ? (
                            <ComboboxItem key={item} value={item}>
                                Create “{item.slice(CREATE_PREFIX.length)}”
                            </ComboboxItem>
                        ) : (
                            <ComboboxItem key={item} value={item}>
                                {item}
                            </ComboboxItem>
                        )
                    }
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    );
}
