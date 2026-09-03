import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance" />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="Appearance" />
                <SettingsPanel
                    variant="inset"
                    title="Color mode"
                    description="Choose the color mode that feels best for your workspace."
                >
                    <div className="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <div className="flex flex-col gap-1">
                            <p className="text-sm font-medium">Theme</p>
                            <p className="text-sm text-muted-foreground">
                                Choose how the interface looks on this device.
                            </p>
                        </div>
                        <AppearanceTabs />
                    </div>
                </SettingsPanel>
            </div>
        </>
    );
}
