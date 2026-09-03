<?php

namespace App\Services;

use App\Models\TeamEmailIntegration;
use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Illuminate\Support\Str;

/**
 * Confirms that an SES connection will actually report campaign feedback.
 *
 * Credentials that can send are not enough: unless the configuration set has an
 * enabled SNS event destination carrying DELIVERY, BOUNCE, and COMPLAINT to the
 * topic this connection stores, every send succeeds and no notification ever
 * arrives. That failure is invisible from the send path, so it is checked here
 * against the SES API while the operator is still looking at the form.
 */
class SesFeedbackVerifier
{
    /** The events campaign reporting and list hygiene are built on. */
    private const array REQUIRED_EVENTS = ['DELIVERY', 'BOUNCE', 'COMPLAINT'];

    /**
     * @return string|null The reason feedback would not arrive, or null when it will.
     */
    public function verify(TeamEmailIntegration $integration): ?string
    {
        $settings = $integration->settings;
        $configurationSet = (string) ($settings['configuration_set'] ?? '');
        $topicArn = (string) ($settings['sns_topic_arn'] ?? '');

        if (blank($configurationSet) || blank($topicArn)) {
            return __('This connection is missing its configuration set or SNS topic.');
        }

        try {
            $destinations = $this->client($settings)
                ->getConfigurationSetEventDestinations(['ConfigurationSetName' => $configurationSet])
                ->get('EventDestinations');
        } catch (AwsException $exception) {
            return $this->describeFailure($exception, $configurationSet);
        }

        $matching = collect(is_array($destinations) ? $destinations : [])
            ->filter(fn (mixed $destination): bool => is_array($destination)
                && ($destination['Enabled'] ?? false) === true
                && $this->publishesTo($destination, $topicArn));

        if ($matching->isEmpty()) {
            return __('Configuration set :set has no enabled Amazon SNS destination publishing to :topic. Bounce and complaint feedback would never arrive.', [
                'set' => $configurationSet,
                'topic' => $topicArn,
            ]);
        }

        $missing = collect(self::REQUIRED_EVENTS)
            ->reject(fn (string $event): bool => $matching->contains(
                fn (array $destination): bool => in_array(
                    $event,
                    array_map(Str::upper(...), (array) ($destination['MatchingEventTypes'] ?? [])),
                    true,
                ),
            ));

        if ($missing->isNotEmpty()) {
            return __('The Amazon SNS destination for :set is not publishing :events. Enable those event types so campaign reporting and unsubscribes stay accurate.', [
                'set' => $configurationSet,
                'events' => $missing->map(Str::title(...))->join(', ', ' and '),
            ]);
        }

        return null;
    }

    /** @param array<string, mixed> $destination */
    private function publishesTo(array $destination, string $topicArn): bool
    {
        $configured = $destination['SnsDestination']['TopicArn'] ?? null;

        return is_string($configured) && hash_equals($topicArn, trim($configured));
    }

    /**
     * Translate an AWS failure into operator-facing guidance.
     *
     * The exception itself is never surfaced or chained: AWS messages echo back
     * request context, and this runs with workspace credentials in scope.
     */
    private function describeFailure(AwsException $exception, string $configurationSet): string
    {
        return match ($exception->getAwsErrorCode()) {
            'NotFoundException' => __('Configuration set :set does not exist in this AWS region.', ['set' => $configurationSet]),
            'AccessDeniedException' => __('These credentials lack ses:GetConfigurationSetEventDestinations, so the feedback setup could not be verified.'),
            default => __('Amazon SES could not be reached to verify the feedback setup for :set.', ['set' => $configurationSet]),
        };
    }

    /**
     * Overridden in tests to supply a client backed by an AWS MockHandler.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function client(array $settings): SesV2Client
    {
        return new SesV2Client([
            'version' => 'latest',
            'region' => (string) ($settings['region'] ?? 'us-east-1'),
            'credentials' => [
                'key' => (string) ($settings['access_key_id'] ?? ''),
                'secret' => (string) ($settings['secret_access_key'] ?? ''),
            ],
        ]);
    }
}
