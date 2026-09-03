<?php

namespace App\Mcp\Tools\Audiences;

use App\Enums\SubscribeFormFieldMode;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Audience;
use App\Rules\AuthorizedSenderAddress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Creates an audience in a Maildun workspace.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('create-audience')]
class CreateAudienceTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'create', [Audience::class, $team]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'first_name_mode' => ['nullable', Rule::enum(SubscribeFormFieldMode::class)],
            'last_name_mode' => ['nullable', Rule::enum(SubscribeFormFieldMode::class)],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email:rfc', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
            'notification_email' => ['nullable', 'email:rfc', 'max:255'],
            'subscribed_url' => ['nullable', 'url:http,https', 'max:2048'],
            'already_subscribed_url' => ['nullable', 'url:http,https', 'max:2048'],
            'unsubscribed_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $attributes = $this->context->blankStringsToNull($validated, [
            'description',
            'from_name',
            'from_address',
            'reply_to',
            'notification_email',
            'subscribed_url',
            'already_subscribed_url',
            'unsubscribed_url',
        ]);

        $audience = $team->audiences()->create($attributes);
        $audience->loadCount(['subscribers', 'segments']);
        $audience->setAttribute('subscribed_count', 0);

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
            'name' => $schema->string()->description('Audience name.')->max(255)->required(),
            'description' => $schema->string()->description('Optional internal description.')->max(2000),
            'first_name_mode' => $schema->string()->description('First-name field behavior on subscribe forms.')->enum(SubscribeFormFieldMode::class),
            'last_name_mode' => $schema->string()->description('Last-name field behavior on subscribe forms.')->enum(SubscribeFormFieldMode::class),
            'from_name' => $schema->string()->description('Default sender name.')->max(255),
            'from_address' => $schema->string()->description('Default authorized sender email address.')->format('email')->max(255),
            'reply_to' => $schema->string()->description('Default reply-to email address.')->format('email')->max(255),
            'notification_email' => $schema->string()->description('Subscription notification email address.')->format('email')->max(255),
            'subscribed_url' => $schema->string()->description('Redirect after subscribing.')->format('uri')->max(2048),
            'already_subscribed_url' => $schema->string()->description('Redirect when already subscribed.')->format('uri')->max(2048),
            'unsubscribed_url' => $schema->string()->description('Redirect after unsubscribing.')->format('uri')->max(2048),
        ];
    }
}
