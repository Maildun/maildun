import { Link, usePage, usePoll } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    CAMPAIGN_STATUS_LABELS,
    campaignStatusVariant,
} from '@/lib/email-status';
import { index, show as showEmail } from '@/routes/emails';

export function NavCampaigns() {
    const { props } = usePage();
    const { recentCampaigns, currentTeam } = props;

    if (!currentTeam || recentCampaigns.length === 0) {
        return null;
    }

    const hasActiveCampaign = recentCampaigns.some(
        (campaign) =>
            campaign.status === 'queued' || campaign.status === 'sending',
    );

    return (
        <SidebarGroup className="px-2 py-0">
            {hasActiveCampaign && <RecentCampaignsPoller />}
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
                                variant={campaignStatusVariant(campaign.status)}
                                className="ml-auto"
                            >
                                {CAMPAIGN_STATUS_LABELS[campaign.status]}
                            </Badge>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

/**
 * Keeps the sidebar badges live while a recent campaign is queued or
 * sending, on any page. It only reloads the shared recentCampaigns prop and
 * unmounts, stopping the poll, once nothing is in flight.
 */
function RecentCampaignsPoller() {
    usePoll(5000, { only: ['recentCampaigns'] }, { mode: 'rest' });

    return null;
}
