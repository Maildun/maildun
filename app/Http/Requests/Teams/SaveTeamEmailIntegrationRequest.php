<?php

namespace App\Http\Requests\Teams;

use App\Enums\EmailProvider;
use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\TeamEmailIntegration;
use App\Services\PublicSmtpHostGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class SaveTeamEmailIntegrationRequest extends FormRequest
{
    private const array SMTP_PORTS = [25, 465, 587, 1025, 2525];

    private const string SNS_TOPIC_ARN_PATTERN = '/\Aarn:aws:sns:([a-z0-9-]+):\d{12}:[A-Za-z0-9_-]+(?:\.fifo)?\z/';

    /** SES is reached through its API, so the credential is an IAM key pair rather than an SMTP login. */
    private const string ACCESS_KEY_ID_PATTERN = '/\A(?:AKIA|ASIA)[A-Z0-9]{16}\z/';

    private const string SECRET_ACCESS_KEY_PATTERN = '/\A[A-Za-z0-9\/+=]{40}\z/';

    protected function prepareForValidation(): void
    {
        foreach (['name', 'smtp_host', 'ses_access_key_id', 'ses_configuration_set', 'ses_sns_topic_arn'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->string($field)->trim()->value()]);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && $this->user()?->hasTeamPermission($team, TeamPermission::UpdateTeam) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $provider = EmailProvider::tryFrom($this->string('provider')->value());
        $currentIntegration = $this->currentIntegration();
        $providerRule = $currentIntegration instanceof TeamEmailIntegration
            && $currentIntegration->provider->isSupported()
            ? Rule::in([$currentIntegration->provider->value])
            : Rule::in(array_map(
                fn (EmailProvider $supportedProvider): string => $supportedProvider->value,
                EmailProvider::supported(),
            ));

        return [
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', $providerRule],
            'trust_provider_senders' => ['nullable', 'boolean'],
            'smtp_host' => [
                Rule::excludeUnless($provider === EmailProvider::Smtp),
                'required',
                'string',
                'max:253',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! PublicSmtpHostGuard::isPermittedSmtpHost($value)) {
                        $fail(__('The :attribute must be a valid hostname.'));
                    }
                },
            ],
            'smtp_port' => [
                Rule::excludeUnless($provider === EmailProvider::Smtp),
                'required',
                'integer',
                Rule::in(self::SMTP_PORTS),
            ],
            'smtp_username' => [Rule::excludeUnless($provider === EmailProvider::Smtp), 'nullable', 'string', 'max:255'],
            'smtp_password' => [Rule::excludeUnless($provider === EmailProvider::Smtp), 'nullable', 'string', 'max:4096'],
            'smtp_encryption' => [
                Rule::excludeUnless($provider === EmailProvider::Smtp),
                'required',
                Rule::in(['tls', 'ssl', 'none']),
            ],
            'ses_region' => [
                Rule::excludeUnless($provider === EmailProvider::AmazonSes),
                'required',
                'string',
                'max:64',
                'regex:/\A(?:af|ap|ca|eu|il|me|mx|sa|us)-[a-z0-9]+(?:-[a-z0-9]+)*-\d\z/',
            ],
            'ses_access_key_id' => [
                Rule::excludeUnless($provider === EmailProvider::AmazonSes),
                'required',
                'string',
                'regex:'.self::ACCESS_KEY_ID_PATTERN,
            ],
            'ses_secret_access_key' => [
                Rule::excludeUnless($provider === EmailProvider::AmazonSes),
                Rule::requiredIf($this->secretIsRequired(EmailProvider::AmazonSes, 'secret_access_key')),
                'nullable',
                'string',
                'regex:'.self::SECRET_ACCESS_KEY_PATTERN,
            ],
            'ses_configuration_set' => [
                Rule::excludeUnless($provider === EmailProvider::AmazonSes),
                'required',
                'string',
                'max:64',
                'regex:/\A[A-Za-z0-9_-]{1,64}\z/',
            ],
            'ses_sns_topic_arn' => [
                Rule::excludeUnless($provider === EmailProvider::AmazonSes),
                'required',
                'string',
                'max:512',
                'regex:'.self::SNS_TOPIC_ARN_PATTERN,
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (EmailProvider::tryFrom($this->string('provider')->value()) !== EmailProvider::AmazonSes
                    || $validator->errors()->hasAny(['ses_region', 'ses_sns_topic_arn'])) {
                    return;
                }

                preg_match(
                    self::SNS_TOPIC_ARN_PATTERN,
                    $this->string('ses_sns_topic_arn')->value(),
                    $matches,
                );

                if (Str::lower($matches[1] ?? '') !== Str::lower($this->string('ses_region')->value())) {
                    $validator->errors()->add(
                        'ses_sns_topic_arn',
                        __('The SNS topic must use the same AWS region as the SES connection.'),
                    );
                }
            },
        ];
    }

    public function emailProvider(): EmailProvider
    {
        return EmailProvider::from($this->string('provider')->value());
    }

    public function connectionName(): string
    {
        return $this->string('name')->trim()->value();
    }

    public function trustsProviderForSenders(): bool
    {
        return $this->boolean('trust_provider_senders');
    }

    /**
     * Get the normalized, provider-specific settings that will be encrypted.
     *
     * @return array<string, int|string|null>
     */
    public function integrationSettings(?TeamEmailIntegration $currentIntegration = null): array
    {
        $provider = $this->emailProvider();
        $current = $currentIntegration?->provider === $provider
            ? $currentIntegration->settings
            : $this->currentSettingsFor($provider);

        return match ($provider) {
            EmailProvider::Smtp => [
                'host' => $this->string('smtp_host')->trim()->lower()->value(),
                'port' => $this->integer('smtp_port'),
                'username' => $this->filled('smtp_username')
                    ? $this->string('smtp_username')->trim()->value()
                    : null,
                'password' => $this->secret('smtp_password', 'password', $current),
                'encryption' => $this->string('smtp_encryption')->value(),
            ],
            EmailProvider::AmazonSes => [
                'region' => $this->string('ses_region')->trim()->lower()->value(),
                'access_key_id' => $this->string('ses_access_key_id')->trim()->value(),
                'secret_access_key' => $this->secret('ses_secret_access_key', 'secret_access_key', $current),
                'configuration_set' => $this->string('ses_configuration_set')->value(),
                'sns_topic_arn' => $this->string('ses_sns_topic_arn')->value(),
            ],
            default => throw new LogicException('Unsupported email provider.'),
        };
    }

    private function secretIsRequired(EmailProvider $provider, string $key): bool
    {
        return blank($this->currentSettingsFor($provider)[$key] ?? null);
    }

    /** @return array<string, mixed> */
    private function currentSettingsFor(EmailProvider $provider): array
    {
        if (EmailProvider::tryFrom($this->string('provider')->value()) !== $provider) {
            return [];
        }

        $integration = $this->currentIntegration();

        return $integration instanceof TeamEmailIntegration && $integration->provider === $provider
            ? $integration->settings
            : [];
    }

    private function currentIntegration(): ?TeamEmailIntegration
    {
        $integration = $this->route('emailIntegration');
        $team = $this->route('team');

        return $integration instanceof TeamEmailIntegration
            && $team instanceof Team
            && $integration->team_id === $team->id
                ? $integration
                : null;
    }

    /**
     * @param  array<string, mixed>  $current
     */
    private function secret(string $input, string $key, array $current): ?string
    {
        if ($this->filled($input)) {
            $value = $this->input($input);

            return is_string($value) ? $value : null;
        }

        $value = $current[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
