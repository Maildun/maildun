<?php

namespace App\Mcp\Tools\Campaigns;

use App\Enums\EmailEditor;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Audience;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Rules\AuthorizedSenderAddress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Creates a draft email campaign in a Maildun workspace.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('create-campaign')]
class CreateCampaignTool extends Tool
{
    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $this->context->authorize($request, 'create', [Email::class, $team]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email:rfc', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
            'html' => ['nullable', 'string', 'max:2000000'],
            'source' => ['nullable', 'string', 'max:2000000'],
            'plain_text' => ['nullable', 'string', 'max:2000000'],
            'query_string' => ['nullable', 'string', 'max:2048', 'regex:/^[^\s?#]+$/'],
            'track_clicks' => ['sometimes', 'boolean'],
            'track_opens' => ['sometimes', 'boolean'],
            'design' => ['nullable', 'array'],
            'design.root' => ['required_with:design', 'array'],
            'audience_uuid' => ['nullable', 'uuid', Rule::exists(Audience::class, 'uuid')->where('team_id', $team->id)],
            'segment_uuid' => ['nullable', 'uuid'],
        ]);

        [$audienceId, $segmentId] = $this->recipientSelection($team, $validated);
        $body = EmailTemplate::blankBodyFor($team->email_editor);
        $attributes = $this->context->blankStringsToNull($validated, [
            'subject', 'preheader', 'from_name', 'from_address', 'reply_to', 'html', 'source', 'plain_text', 'query_string',
        ]);

        unset($attributes['audience_uuid'], $attributes['segment_uuid']);

        $campaign = $team->emails()->create([
            ...$attributes,
            'subject' => $attributes['subject'] ?? $attributes['name'],
            'editor' => $team->email_editor,
            'html' => $attributes['html'] ?? $body['html'],
            'source' => $team->email_editor->usesSource()
                ? ($attributes['source'] ?? $body['source'])
                : null,
            'plain_text' => $team->email_editor === EmailEditor::PlainText
                ? ($attributes['plain_text'] ?? $attributes['source'] ?? null)
                : ($attributes['plain_text'] ?? null),
            'design' => $team->email_editor === EmailEditor::Builder
                ? ($attributes['design'] ?? $body['design'])
                : null,
            'audience_id' => $audienceId,
            'segment_id' => $segmentId,
        ]);
        $campaign->loadMissing(['audience:id,uuid,name', 'segment:id,uuid,name']);

        return Response::structured([
            'workspace' => $this->payload->workspace($team),
            'campaign' => $this->payload->campaign($campaign),
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
            'name' => $schema->string()->description('Campaign name.')->max(255)->required(),
            'subject' => $schema->string()->description('Email subject; defaults to the campaign name.')->max(255),
            'preheader' => $schema->string()->description('Preview text.')->max(255),
            'from_name' => $schema->string()->description('Sender name.')->max(255),
            'from_address' => $schema->string()->description('Authorized sender email.')->format('email')->max(255),
            'reply_to' => $schema->string()->description('Reply-to email.')->format('email')->max(255),
            'html' => $schema->string()->description('Rendered HTML email content.')->max(2000000),
            'source' => $schema->string()->description('Editable Markdown or plain-text source for source-based workspace editors.')->max(2000000),
            'plain_text' => $schema->string()->description('Optional plain-text content.')->max(2000000),
            'query_string' => $schema->string()->description('Tracking query string without a leading question mark.')->max(2048),
            'track_clicks' => $schema->boolean()->description('Whether click tracking is enabled.'),
            'track_opens' => $schema->boolean()->description('Whether open tracking is enabled.'),
            'design' => $schema->object()->description('EmailBuilder.js design document; root is required when supplied.'),
            'audience_uuid' => $schema->string()->description('Audience UUID for campaign recipients.')->format('uuid'),
            'segment_uuid' => $schema->string()->description('Segment UUID belonging to the selected audience.')->format('uuid'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{int|null, int|null}
     */
    private function recipientSelection(Team $team, array $validated): array
    {
        $audienceUuid = $validated['audience_uuid'] ?? null;
        $segmentUuid = $validated['segment_uuid'] ?? null;

        if ($segmentUuid !== null && $audienceUuid === null) {
            throw ValidationException::withMessages([
                'segment_uuid' => 'Choose an audience before choosing a segment.',
            ]);
        }

        $audience = $audienceUuid === null
            ? null
            : $team->audiences()->where('uuid', $audienceUuid)->firstOrFail();
        $segment = $segmentUuid === null
            ? null
            : $audience?->segments()->where('uuid', $segmentUuid)->first();

        if ($segmentUuid !== null && $segment === null) {
            throw ValidationException::withMessages([
                'segment_uuid' => 'The selected segment is not part of that audience.',
            ]);
        }

        return [$audience?->id, $segment?->id];
    }
}
