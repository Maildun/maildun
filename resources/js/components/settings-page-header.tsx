type SettingsPageHeaderProps = {
    eyebrow?: string;
    title: string;
    description?: string;
};

export function SettingsPageHeader({
    eyebrow,
    title,
    description,
}: SettingsPageHeaderProps) {
    return (
        <header className="flex flex-col gap-1.5 px-1">
            {eyebrow ? (
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {eyebrow}
                </p>
            ) : null}
            <h1 className="font-heading text-2xl font-semibold tracking-tight">
                {title}
            </h1>
            {description ? (
                <p className="max-w-2xl text-sm text-muted-foreground">
                    {description}
                </p>
            ) : null}
        </header>
    );
}
