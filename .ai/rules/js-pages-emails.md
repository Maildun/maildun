---
paths:
  - 'app/{Actions/Emails,Jobs,Mail,Http/Controllers}/** resources/js/pages/emails/** config/{mail,services}.php'
---

# Js Pages Emails

## Campaign delivery is SES first with local SMTP
Production campaign delivery uses Laravel's Amazon SES transport and verified SES to SNS feedback. Local development uses SMTP. Maildun owns open and click tracking; do not enable or count SES open and click events because that would duplicate metrics. Send one SES message per recipient and correlate with campaign_uuid and delivery_uuid tags.

## Failed sends retry; permanent bounces do not
Job retries (3 tries, backoff) are automatic. After the campaign finishes, failed, rejected, and delayed deliveries can be queued again from the report (bulk or per recipient). Permanent SES bounces and complaints unsubscribe the subscriber and must not be retried. Transient bounces become Delayed and stay subscribed so they can be retried.

## Campaign Overview has no activity chart
emails/show Overview is insights plus delivery health. Do not bring back CampaignOverviewChart or an overview.series prop — unique open/click rates already live in the report metric cards and Human engagement.

## Recipient status tabs hide vertical overflow
Recipient activity wraps RecipientStatusTabs in overflow-x-auto overflow-y-hidden. overflow-x-auto alone computes overflow-y to auto and shows a vertical scrollbar beside the sliding pill. Keep overflow-y-hidden.

## Campaign report metric cards match the dashboard
Recipients/Delivered/Unique opens/Unique clicks use the dashboard MetricCard layout: CardDescription, CardAction icon tile (size-8 rounded-lg bg-muted), CardTitle text-2xl tabular-nums. Icons are UserGroup, MailSend01, MailOpen01, MouseLeftClick01. Delivery health is a size="sm" Card with a border-b header and icon stats; Failed/Bounced/Complaints use bg-destructive/10 text-destructive when the count is above zero.

## Maildun tracks opens and clicks first-party
Each sent campaign injects a signed 1x1 open.gif before </body> and rewrites http(s) links through a signed redirect. Those public GET routes must stay signed and unauthenticated. SES Open/Click events are stored but never counted. A click with opens_count 0 also records an open so blocked pixels still produce unique opens.

## Snapshot campaign personalization and honor tracking controls
Snapshot subscriber, date, and custom-field merge data onto each EmailDelivery when queueing. Apply the campaign query string before link extraction/tracking. Track-clicks controls redirect links and EmailLink rows; track-opens controls the pixel and click-as-open. Keep campaign attachments private and editable only while the campaign is a draft.
