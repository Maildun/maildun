import { useMemo, useState } from 'react';
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxItem,
    ComboboxList,
    ComboboxValue,
    useComboboxAnchor,
} from '@/components/ui/combobox';
import { cn } from '@/lib/utils';

const CREATE_PREFIX = '__create__:';

type TagOption = {
    name: string;
    color?: string | null;
};

type TagsComboboxProps = {
    id?: string;
    value: string[];
    onValueChange: (names: string[]) => void;
    availableTags: TagOption[];
    placeholder?: string;
    className?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
};

export function TagsCombobox({
    id,
    value,
    onValueChange,
    availableTags,
    placeholder = 'Search or create a tag…',
    className,
    disabled,
    'aria-invalid': ariaInvalid,
}: TagsComboboxProps) {
    const anchor = useComboboxAnchor();
    const [inputValue, setInputValue] = useState('');

    const colorByName = useMemo(() => {
        const map = new Map<string, string | null>();
        availableTags.forEach((tag) =>
            map.set(tag.name.toLowerCase(), tag.color ?? null),
        );

        return map;
    }, [availableTags]);

    const selectedLower = useMemo(
        () => new Set(value.map((name) => name.toLowerCase())),
        [value],
    );

    const trimmedInput = inputValue.trim();
    const lowerInput = trimmedInput.toLowerCase();

    // Selected tags stay in the list so their tick indicator shows and they can
    // be toggled off from the dropdown as well as from their chip.
    const matches = useMemo(
        () =>
            availableTags
                .filter(
                    (tag) =>
                        !trimmedInput ||
                        tag.name.toLowerCase().includes(lowerInput),
                )
                .map((tag) => tag.name),
        [availableTags, trimmedInput, lowerInput],
    );

    const canCreate =
        trimmedInput.length > 0 &&
        !selectedLower.has(lowerInput) &&
        !availableTags.some((tag) => tag.name.toLowerCase() === lowerInput);

    const items = useMemo(
        () =>
            canCreate
                ? [...matches, `${CREATE_PREFIX}${trimmedInput}`]
                : matches,
        [matches, canCreate, trimmedInput],
    );

    const colorFor = (name: string) =>
        colorByName.get(name.toLowerCase()) ?? null;

    /**
     * Selecting the synthetic "create" item yields a prefixed value, so unwrap it
     * back to the plain tag name before it reaches the form. Names are also
     * deduped case-insensitively to mirror ManageContact::syncTags().
     */
    const handleValueChange = (next: string[]) => {
        const names: string[] = [];
        const seen = new Set<string>();

        next.forEach((item) => {
            const name = (
                item.startsWith(CREATE_PREFIX)
                    ? item.slice(CREATE_PREFIX.length)
                    : item
            ).trim();

            if (!name || seen.has(name.toLowerCase())) {
                return;
            }

            seen.add(name.toLowerCase());
            names.push(name);
        });

        onValueChange(names);

        if (names.length > value.length) {
            setInputValue('');
        }
    };

    return (
        <Combobox
            multiple
            autoHighlight
            items={items}
            filter={null}
            value={value}
            onValueChange={handleValueChange}
            inputValue={inputValue}
            onInputValueChange={setInputValue}
            disabled={disabled}
        >
            <ComboboxChips ref={anchor} className={cn('w-full', className)}>
                <ComboboxValue>
                    {(names: string[]) => (
                        <>
                            {names.map((name) => {
                                const color = colorFor(name);

                                return (
                                    <ComboboxChip key={name}>
                                        {color ? (
                                            <span
                                                className="size-1.5 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor: color,
                                                }}
                                            />
                                        ) : null}
                                        {name}
                                    </ComboboxChip>
                                );
                            })}
                            <ComboboxChipsInput
                                id={id}
                                placeholder={
                                    names.length ? undefined : placeholder
                                }
                                aria-invalid={ariaInvalid}
                            />
                        </>
                    )}
                </ComboboxValue>
            </ComboboxChips>
            <ComboboxContent anchor={anchor}>
                <ComboboxEmpty>No tags found.</ComboboxEmpty>
                <ComboboxList>
                    {(item: string) => {
                        if (item.startsWith(CREATE_PREFIX)) {
                            return (
                                <ComboboxItem key={item} value={item}>
                                    Create “{item.slice(CREATE_PREFIX.length)}”
                                </ComboboxItem>
                            );
                        }

                        const color = colorFor(item);

                        return (
                            <ComboboxItem key={item} value={item}>
                                {color ? (
                                    <span
                                        className="size-2 shrink-0 rounded-full"
                                        style={{ backgroundColor: color }}
                                    />
                                ) : null}
                                {item}
                            </ComboboxItem>
                        );
                    }}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    );
}
