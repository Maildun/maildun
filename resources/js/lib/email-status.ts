import type { EmailCampaignStatus, EmailDeliveryStatus } from '@/types';

/**
 * Badge variants used for campaign and delivery statuses. Kept to the subset
 * of Badge variants these statuses need.
 */
export type EmailStatusBadgeVariant =
    'secondary' | 'info' | 'success' | 'destructive';

export const CAMPAIGN_STATUS_LABELS: Record<EmailCampaignStatus, string> = {
    draft: 'Draft',
    queued: 'Queued',
    sending: 'Sending',
    sent: 'Sent',
    partially_failed: 'Partially failed',
    failed: 'Failed',
};

/**
 * One colour per campaign status everywhere a campaign badge is shown: in
 * flight is info, finished is success, any failure is destructive.
 */
export function campaignStatusVariant(
    status: EmailCampaignStatus,
): EmailStatusBadgeVariant {
    switch (status) {
        case 'sent':
            return 'success';
        case 'failed':
        case 'partially_failed':
            return 'destructive';
        case 'queued':
        case 'sending':
            return 'info';
        default:
            return 'secondary';
    }
}

export const DELIVERY_STATUS_LABELS: Record<EmailDeliveryStatus, string> = {
    queued: 'Queued',
    sending: 'Sending',
    sent: 'Sent',
    delivered: 'Delivered',
    delayed: 'Delayed',
    bounced: 'Bounced',
    complained: 'Complained',
    rejected: 'Rejected',
    failed: 'Failed',
};

/**
 * Delivered is the only confirmed success; bounces, complaints, rejections
 * and failures are destructive; everything else is still in progress.
 */
export function deliveryStatusVariant(
    status: EmailDeliveryStatus,
): EmailStatusBadgeVariant {
    switch (status) {
        case 'delivered':
            return 'success';
        case 'bounced':
        case 'complained':
        case 'rejected':
        case 'failed':
            return 'destructive';
        default:
            return 'secondary';
    }
}
