<?php

namespace App\Services;

use App\Enums\EmailProvider;

final readonly class ResolvedEmailTransport
{
    /**
     * @param  array<string, mixed>|null  $configuration
     * @param  list<string>|null  $authorizedFromAddresses
     */
    public function __construct(
        public EmailProvider $provider,
        public ?int $integrationId,
        public ?string $integrationUuid,
        public ?string $integrationName,
        public ?array $configuration,
        public ?string $sesConfigurationSet,
        public ?string $sesSnsTopicArnHash,
        public ?array $authorizedFromAddresses = null,
    ) {}

    public function usesTeamEmailIntegration(): bool
    {
        return $this->integrationId !== null;
    }

    /**
     * Whether this transport may carry the given From address.
     *
     * Workspace connections authorize registered sender addresses exactly. A
     * null list belongs only to a non-workspace transport and is unconstrained.
     */
    public function allowsSender(?string $address): bool
    {
        if ($this->authorizedFromAddresses === null) {
            return true;
        }

        if (blank($address)) {
            return false;
        }

        $candidate = strtolower(trim((string) $address));

        return in_array($candidate, array_map(
            fn (string $authorized): string => strtolower(trim($authorized)),
            $this->authorizedFromAddresses,
        ), true);
    }
}
