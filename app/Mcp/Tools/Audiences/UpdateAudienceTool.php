<?php

namespace App\Mcp\Tools\Audiences;

use App\Enums\SubscribeFormFieldMode;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberStatus;
use App\Mcp\Support\AudienceLanguageSettings;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
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

#[Description('Updates selected fields on an audience in a Maildun workspace.')]
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
        ]);

        $changes = $this->context->changes($validated, [...self::FIELDS, ...AudienceLanguageSettings::FIELDS]);
        $changes = $this->context->blankStringsToNull($changes, [
            'description',
            'from_name',
            'from_address',
            'reply_to',
            'notification_email',
            'unsubscribed_url',
        ]);

        $audience->update([
            ...Arr::except($changes, AudienceLanguageSettings::FIELDS),
            ...$this->languageSettings->resolve($validated, $audience),
        ]);
        $audience->refresh();
        $audience->loadCount([
            'subscribers',
            'subscribers as subscribed_count' => fn ($query) => $query->where('status', SubscriberStatus::Subscribed),
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
        ];
    }
}
