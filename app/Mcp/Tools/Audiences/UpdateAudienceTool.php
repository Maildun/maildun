<?php

namespace App\Mcp\Tools\Audiences;

use App\Enums\SubscribeFormFieldMode;
use App\Enums\SubscriberLanguage;
use App\Enums\TransactionalEmailStatus;
use App\Mcp\Support\AudienceLanguageSettings;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\TransactionalEmail;
use App\Rules\AuthorizedSenderAddress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Updates selected fields on an audience in a Maildun workspace, including double opt-in. Turning double opt-in on needs a published transactional email to send the confirmation link.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('update-audience')]
class UpdateAudienceTool extends Tool
{
    private const array FIELDS = [
        'name',
        'description',
        'first_name_mode',
        'last_name_mode',
        'from_name',
        'from_address',
        'reply_to',
        'notification_email',
        'unsubscribed_url',
    ];

    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
        private AudienceLanguageSettings $languageSettings,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $audience = $this->context->audience($request, $team);
        $this->context->authorize($request, 'update', $audience);
        $doubleOptIn = $request->get('double_opt_in');
        $keepsDoubleOptIn = is_bool($doubleOptIn) ? $doubleOptIn : $audience->double_opt_in;
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'first_name_mode' => ['sometimes', Rule::enum(SubscribeFormFieldMode::class)],
            'last_name_mode' => ['sometimes', Rule::enum(SubscribeFormFieldMode::class)],
            ...$this->languageSettings->rules(),
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_address' => ['sometimes', 'nullable', 'email:rfc', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'notification_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'unsubscribed_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'double_opt_in' => ['sometimes', 'boolean'],
            'double_opt_in_email_uuid' => [
                Rule::requiredIf($keepsDoubleOptIn && (
                    $audience->double_opt_in_email_id === null
                    || array_key_exists('double_opt_in_email_uuid', $request->all())
                )),
                'nullable',
                'string',
                Rule::exists(TransactionalEmail::class, 'uuid')->where(fn ($query) => $query
                    ->where('team_id', $team->id)
                    ->where('status', TransactionalEmailStatus::Published->value)
                    ->whereNull('deleted_at')),
            ],
        ], [
            'double_opt_in_email_uuid.required' => 'Double opt-in needs a published transactional email for the confirmation link.',
            'double_opt_in_email_uuid.exists' => 'Choose a published transactional email from this workspace.',
        ]);

        $changes = $this->context->changes($validated, [...self::FIELDS, ...AudienceLanguageSettings::FIELDS, 'double_opt_in', 'double_opt_in_email_uuid']);
        $changes = $this->context->trimStrings($changes, [
            'name',
            'description',
            'from_name',
            'from_address',
            'reply_to',
            'notification_email',
            'unsubscribed_url',
        ]);

        if (array_key_exists('double_opt_in_email_uuid', $changes)) {
            $changes['double_opt_in_email_id'] = $changes['double_opt_in_email_uuid'] === null
                ? null
                : $team->transactionalEmails()->where('uuid', $changes['double_opt_in_email_uuid'])->value('id');
        }

        if (! $keepsDoubleOptIn) {
            $changes['double_opt_in_email_id'] = null;
        }

        $audience->update([
            ...Arr::except($changes, [...AudienceLanguageSettings::FIELDS, 'double_opt_in_email_uuid']),
            ...$this->languageSettings->resolve($validated, $audience),
        ]);
        $audience->refresh()->load(['doubleOptInEmail:id,uuid,name', 'audienceAttributes']);
        $audience->loadCount([
            'subscribers',
            'subscribers as subscribed_count' => fn ($query) => $query->optedIn(),
            'segments',
        ]);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'audience' => $this->payload->audience($audience),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace' => $schema->string()->description('Workspace slug.')->max(255)->required(),
            'uuid' => $schema->string()->description('Audience UUID.')->format('uuid')->required(),
            'name' => $schema->string()->description('Audience name.')->max(255),
            'description' => $schema->string()->description('Internal description.')->max(2000)->nullable(),
            'first_name_mode' => $schema->string()->description('First-name field behavior.')->enum(SubscribeFormFieldMode::class),
            'last_name_mode' => $schema->string()->description('Last-name field behavior.')->enum(SubscribeFormFieldMode::class),
            'language_mode' => $schema->string()->description('Language field behavior on subscribe forms. optional or required needs allowed_languages and default_language.')->enum(SubscribeFormFieldMode::class),
            'allowed_languages' => $schema->array()->description('ISO 639-1 language codes subscribers may choose; also limits languages accepted by the API and add-subscriber.')->items($schema->string()->enum(SubscriberLanguage::class)),
            'default_language' => $schema->string()->description('Language for new subscriptions that give none. Must be in allowed_languages.')->enum(SubscriberLanguage::class)->nullable(),
            'from_name' => $schema->string()->description('Default sender name.')->max(255)->nullable(),
            'from_address' => $schema->string()->description('Default authorized sender email.')->format('email')->max(255)->nullable(),
            'reply_to' => $schema->string()->description('Default reply-to email.')->format('email')->max(255)->nullable(),
            'notification_email' => $schema->string()->description('Subscription notification email.')->format('email')->max(255)->nullable(),
            'unsubscribed_url' => $schema->string()->description('Redirect after unsubscribing.')->format('uri')->max(2048)->nullable(),
            'double_opt_in' => $schema->boolean()->description('Require new subscribers to confirm by email before they receive campaigns.'),
            'double_opt_in_email_uuid' => $schema->string()->description('UUID of the published transactional email that sends the confirmation link. Required when turning double opt-in on.')->format('uuid')->nullable(),
        ];
    }
}
