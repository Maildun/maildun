import { Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index, show as showEmail } from '@/routes/emails';
import type { RecentCampaign } from '@/types';

const STATUS_LABELS: Record<RecentCampaign['status'], string> = {
    draft: 'Draft',
    queued: 'Queued',
    sending: 'Sending',
    sent: 'Sent',
    partially_failed: 'Partially failed',
    failed: 'Failed',
};

const STATUS_VARIANTS: Record<
    RecentCampaign['status'],
    'default' | 'secondary' | 'success' | 'destructive'
> = {
    draft: 'default',
    queued: 'secondary',
    sending: 'secondary',
    sent: 'success',
    partially_failed: 'secondary',
    failed: 'destructive',
};

export function NavCampaigns() {
    const { props } = usePage();
    const { recentCampaigns, currentTeam } = props;

    if (!currentTeam || recentCampaigns.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel
                className="hover:text-sidebar-foreground"
                render={<Link href={index(currentTeam.slug)} prefetch />}
            >
                Recent campaigns
            </SidebarGroupLabel>
            <SidebarMenu>
                {recentCampaigns.map((campaign) => (
                    <SidebarMenuItem key={campaign.uuid}>
                        <SidebarMenuButton
                            tooltip={{ children: campaign.name }}
                            className="text-sidebar-foreground/70"
                            render={
                                <Link
                                    href={showEmail([
                                        currentTeam.slug,
                                        campaign.uuid,
                                    ])}
                                    prefetch
                                />
                            }
                        >
                            <span className="truncate">{campaign.name}</span>
                            <Badge
                                variant={STATUS_VARIANTS[campaign.status]}
                                className="ml-auto"
                            >
                                {STATUS_LABELS[campaign.status]}
                            </Badge>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
