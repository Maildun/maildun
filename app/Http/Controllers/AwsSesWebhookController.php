<?php

namespace App\Http\Controllers;

use App\Actions\Emails\DispatchEmailProviderEvent;
use App\Actions\Emails\ProcessSesEvent;
use App\Enums\EmailProvider;
use App\Models\AutomationEmailDelivery;
use App\Models\EmailDeliveryAttempt;
use App\Models\TeamEmailIntegration;
use App\Models\TransactionalEmailDelivery;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class AwsSesWebhookController extends Controller
{
    /**
     * A notification is stored as soon as its signature and topic check out,
     * and only then queued for processing. SNS gives up redelivering within
     * minutes, so processing inline would lose a bounce or complaint to any
     * failure; once stored, the queue and emails:dispatch-provider-events keep
     * trying, and the event id still de-duplicates redeliveries.
     */
    public function __invoke(Request $request, ProcessSesEvent $processor, DispatchEmailProviderEvent $dispatchEvent): Response
    {
        try {
            $message = Message::fromJsonString($request->getContent());
        } catch (InvalidArgumentException|RuntimeException) {
            abort(400);
        }

        $topicArn = trim((string) $message['TopicArn']);
        abort_unless(filled($topicArn) && $this->isAllowedTopic($topicArn), 403);

        try {
            (new MessageValidator(
                fn (string $url): string => Cache::remember(
                    'aws-sns-signing-certificate:'.hash('sha256', $url),
                    now()->addDay(),
                    fn (): string => Http::timeout(5)->get($url)->throw()->body(),
                ),
            ))->validate($message);
        } catch (InvalidSnsMessageException) {
            abort(403);
        }

        if ($message['Type'] === 'SubscriptionConfirmation') {
            $url = (string) $message['SubscribeURL'];
            $host = parse_url($url, PHP_URL_HOST);

            abort_unless(parse_url($url, PHP_URL_SCHEME) === 'https' && is_string($host) && preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/i', $host), 422);
            Http::timeout(5)->get($url)->throw();
        } elseif ($message['Type'] === 'Notification') {
            $payload = json_decode((string) $message['Message'], true, flags: JSON_THROW_ON_ERROR);
            abort_unless(is_array($payload), 422);
            $event = $processor->record((string) $message['MessageId'], $payload, $topicArn);

            if ($event->processed_at === null) {
                $dispatchEvent->handle($event);
            }
        }

        return response()->noContent();
    }

    private function isAllowedTopic(string $topicArn): bool
    {
        $topicArnHash = hash('sha256', $topicArn);
        $globalTopicArn = config('services.ses.sns_topic_arn');

        if (is_string($globalTopicArn)
            && filled($globalTopicArn)
            && hash_equals(hash('sha256', trim($globalTopicArn)), $topicArnHash)) {
            return true;
        }

        $sesProviders = EmailProvider::withSesFeedback();

        return TeamEmailIntegration::query()
            ->whereIn('provider', $sesProviders)
            ->where('ses_sns_topic_arn_hash', $topicArnHash)
            ->exists()
            || EmailDeliveryAttempt::query()
                ->whereIn('provider', $sesProviders)
                ->where('ses_sns_topic_arn_hash', $topicArnHash)
                ->exists()
            || TransactionalEmailDelivery::query()
                ->whereIn('provider', $sesProviders)
                ->where('ses_sns_topic_arn_hash', $topicArnHash)
                ->exists()
            || AutomationEmailDelivery::query()
                ->whereIn('provider', $sesProviders)
                ->where('ses_sns_topic_arn_hash', $topicArnHash)
                ->exists();
    }
}
