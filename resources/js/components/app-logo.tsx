import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

type AppLogoProps = {
    showName?: boolean;
};

export default function AppLogo({ showName = true }: AppLogoProps) {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center">
                <AppLogoIcon mode="theme" className="size-6" />
            </div>
            {showName && (
                <div className="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
                    <span className="mb-0.5 truncate leading-tight font-semibold">
                        {name}
                    </span>
                </div>
            )}
        </>
    );
}
