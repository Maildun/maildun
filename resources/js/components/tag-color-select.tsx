import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { tagColorLabel } from '@/lib/tags';
import { cn } from '@/lib/utils';

export function TagColorSwatch({
    color,
    className,
}: {
    color: string | null;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn('size-2.5 shrink-0 rounded-[2px]', className)}
            style={{ backgroundColor: color ?? 'var(--muted-foreground)' }}
        />
    );
}

type TagColorSelectProps = {
    id?: string;
    name?: string;
    value: string;
    onValueChange: (color: string) => void;
    colors: string[];
    'aria-invalid'?: boolean;
};

export function TagColorSelect({
    id,
    name,
    value,
    onValueChange,
    colors,
    'aria-invalid': ariaInvalid,
}: TagColorSelectProps) {
    // A tag saved with a color outside the palette still needs an option to sit on.
    const options = colors.includes(value) ? colors : [value, ...colors];

    return (
        <Select
            name={name}
            value={value}
            onValueChange={(next) => {
                if (next) {
                    onValueChange(next);
                }
            }}
        >
            <SelectTrigger
                id={id}
                data-test="tag-color-select"
                className="w-full"
                aria-invalid={ariaInvalid}
            >
                <SelectValue>
                    <TagColorSwatch color={value} />
                    {tagColorLabel(value)}
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                {options.map((color) => (
                    <SelectItem
                        key={color}
                        value={color}
                        data-test="tag-color-option"
                    >
                        <TagColorSwatch color={color} />
                        {tagColorLabel(color)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
