<?php

namespace App\Mcp\Tools\Campaigns;

use App\Enums\EmailEditor;
use App\Enums\EmailStatus;
use App\Mcp\Support\ResourcePayload;
use App\Mcp\Support\WorkspaceContext;
use App\Models\Audience;
use App\Models\Email;
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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Updates selected fields on a draft email campaign in a Maildun workspace.')]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
#[IsReadOnly(false)]
#[Name('update-campaign')]
class UpdateCampaignTool extends Tool
{
    private const array FIELDS = [
        'name', 'subject', 'preheader', 'from_name', 'from_address', 'reply_to', 'html', 'source', 'plain_text',
        'query_string', 'track_clicks', 'track_opens', 'design', 'audience_uuid', 'segment_uuid',
    ];

    public function __construct(
        private WorkspaceContext $context,
        private ResourcePayload $payload,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $team = $this->context->team($request);
        $campaign = $this->context->campaign($request, $team);
        $this->context->authorize($request, 'update', $campaign);

        if ($campaign->status !== EmailStatus::Draft) {
            throw ValidationException::withMessages([
                'uuid' => 'Only draft campaigns can be updated.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'preheader' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_address' => ['sometimes', 'nullable', 'email:rfc', 'max:255', new AuthorizedSenderAddress($team)],
            'reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'html' => ['sometimes', 'required', 'string', 'max:2000000'],
            'source' => ['sometimes', 'required', 'string', 'max:2000000'],
            'plain_text' => ['sometimes', 'nullable', 'string', 'max:2000000'],
            'query_string' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^[^\s?#]+$/'],
            'track_clicks' => ['sometimes', 'boolean'],
            'track_opens' => ['sometimes', 'boolean'],
            'design' => ['sometimes', 'nullable', 'array'],
            'design.root' => ['required_with:design', 'array'],
            'audience_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists(Audience::class, 'uuid')->where('team_id', $team->id)],
            'segment_uuid' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $changes = $this->context->changes($validated, self::FIELDS);
        $changes = $this->context->blankStringsToNull($changes, [
            'preheader', 'from_name', 'from_address', 'reply_to', 'plain_text', 'query_string',
        ]);
        [$audienceId, $segmentId] = $this->recipientSelection($team, $campaign, $changes);

        unset($changes['audience_uuid'], $changes['segment_uuid']);

        if (array_key_exists('design', $changes) && $team->email_editor !== EmailEditor::Builder) {
            $changes['design'] = null;
        }

        $changes['source'] = $team->email_editor->usesSource()
            ? ($changes['source'] ?? $campaign->source)
            : null;

        if ($team->email_editor === EmailEditor::PlainText) {
            $changes['plain_text'] = $changes['source'];
        }

        $campaign->update([
            ...$changes,
            'editor' => $team->email_editor,
            'audience_id' => $audienceId,
            'segment_id' => $segmentId,
        ]);
        $campaign->refresh()->loadMissing(['audience:id,uuid,name', 'segment:id,uuid,name']);

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
            'uuid' => $schema->string()->description('Campaign UUID.')->format('uuid')->required(),
            'name' => $schema->string()->description('Campaign name.')->max(255),
            'subject' => $schema->string()->description('Email subject.')->max(255),
            'preheader' => $schema->string()->description('Preview text.')->max(255)->nullable(),
            'from_name' => $schema->string()->description('Sender name.')->max(255)->nullable(),
            'from_address' => $schema->string()->description('Authorized sender email.')->format('email')->max(255)->nullable(),
            'reply_to' => $schema->string()->description('Reply-to email.')->format('email')->max(255)->nullable(),
            'html' => $schema->string()->description('Rendered HTML content.')->max(2000000),
            'source' => $schema->string()->description('Editable Markdown or plain-text source for source-based workspace editors.')->max(2000000),
            'plain_text' => $schema->string()->description('Plain-text content.')->max(2000000)->nullable(),
            'query_string' => $schema->string()->description('Tracking query string without a leading question mark.')->max(2048)->nullable(),
            'track_clicks' => $schema->boolean()->description('Whether click tracking is enabled.'),
            'track_opens' => $schema->boolean()->description('Whether open tracking is enabled.'),
            'design' => $schema->object()->description('EmailBuilder.js design document.')->nullable(),
            'audience_uuid' => $schema->string()->description('Audience UUID, or null to clear recipients.')->format('uuid')->nullable(),
            'segment_uuid' => $schema->string()->description('Segment UUID belonging to the selected audience, or null to clear it.')->format('uuid')->nullable(),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{int|null, int|null}
     */
    private function recipientSelection(Team $team, Email $campaign, array $changes): array
    {
        $audienceChanged = array_key_exists('audience_uuid', $changes);
        $segmentChanged = array_key_exists('segment_uuid', $changes);

        $audience = $audienceChanged
            ? ($changes['audience_uuid'] === null
                ? null
                : $team->audiences()->where('uuid', $changes['audience_uuid'])->firstOrFail())
            : $campaign->audience;

        if ($segmentChanged && $changes['segment_uuid'] !== null && $audience === null) {
            throw ValidationException::withMessages([
                'segment_uuid' => 'Choose an audience before choosing a segment.',
            ]);
        }

        if ($segmentChanged) {
            $segment = $changes['segment_uuid'] === null
                ? null
                : $audience?->segments()->where('uuid', $changes['segment_uuid'])->first();
        } elseif ($audienceChanged) {
            $segment = null;
        } else {
            $segment = $campaign->segment;
        }

        if ($segmentChanged && $changes['segment_uuid'] !== null && $segment === null) {
            throw ValidationException::withMessages([
                'segment_uuid' => 'The selected segment is not part of that audience.',
            ]);
        }

        return [$audience?->id, $segment?->id];
    }
}
