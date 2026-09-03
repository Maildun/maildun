import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

export type PreviewWidth = 'desktop' | 'mobile';

const PREVIEW_WIDTHS = [
    { value: 'desktop', label: 'Desktop' },
    { value: 'mobile', label: 'Mobile' },
] as const;

type Props = {
    value: PreviewWidth;
    onValueChange: (value: PreviewWidth) => void;
    testIdPrefix?: string;
};

export default function PreviewWidthTabs({
    value,
    onValueChange,
    testIdPrefix = 'preview-width',
}: Props) {
    return (
        <Tabs
            value={value}
            onValueChange={(next) => {
                if (next === 'desktop' || next === 'mobile') {
                    onValueChange(next);
                }
            }}
        >
            <TabsList variant="sliding" aria-label="Preview width">
                {PREVIEW_WIDTHS.map((width) => (
                    <TabsTrigger
                        key={width.value}
                        value={width.value}
                        data-test={`${testIdPrefix}-${width.value}`}
                    >
                        {width.label}
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}
