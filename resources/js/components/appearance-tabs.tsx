import {
    ComputerIcon,
    Moon02Icon,
    Sun01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { IconSvgElement } from '@hugeicons/react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

export default function AppearanceToggleTab() {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: {
        value: Appearance;
        icon: IconSvgElement;
        label: string;
    }[] = [
        { value: 'light', icon: Sun01Icon, label: 'Light' },
        { value: 'dark', icon: Moon02Icon, label: 'Dark' },
        { value: 'system', icon: ComputerIcon, label: 'System' },
    ];

    return (
        <Tabs
            value={appearance}
            className="shrink-0"
            onValueChange={(value) => updateAppearance(value as Appearance)}
        >
            <TabsList variant="sliding" aria-label="Color mode">
                {tabs.map(({ value, icon, label }) => (
                    <TabsTrigger key={value} value={value}>
                        <HugeiconsIcon icon={icon} />
                        {label}
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}
