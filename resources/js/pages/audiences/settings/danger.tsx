import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { DeleteAudienceDialog } from '@/components/delete-audience-dialog';
import Heading from '@/components/heading';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import AudienceSettingsLayout from '@/layouts/audiences/settings-layout';
import type { Audience, AudienceSummary } from '@/types/audiences';

type Props = {
    audience: Audience &
        Pick<
            AudienceSummary,
            'subscribers_count' | 'segments_count' | 'forms_count'
        >;
};

export default function AudienceDangerSettings({ audience }: Props) {
    const { currentTeam } = usePage().props;
    const [deleteOpen, setDeleteOpen] = useState(false);

    if (!currentTeam) {
        return null;
    }

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`Danger zone · ${audience.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="Danger zone"
                    description="Permanent actions for this audience. These cannot be undone."
                />

                <SettingsPanel
                    variant="inset"
                    title="Delete audience"
                    description="Remove this audience and everything in it."
                >
                    <div className="flex w-full flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-8 sm:px-7">
                        <p className="text-sm text-muted-foreground">
                            This permanently removes every subscriber, segment,
                            and form.
                        </p>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            className="shrink-0"
                            onClick={() => setDeleteOpen(true)}
                        >
                            Delete
                        </Button>
                    </div>
                </SettingsPanel>

                <DeleteAudienceDialog
                    teamSlug={currentTeam.slug}
                    audience={audience}
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                />
            </div>
        </AudienceSettingsLayout>
    );
}
