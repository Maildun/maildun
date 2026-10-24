<?php

namespace App\Http\Controllers;

use App\Actions\Emails\ConsumeAssistantRequest;
use App\Actions\Emails\ResolveBuilderMergeTags;
use App\Actions\Media\SearchReadyTeamMedia;
use App\Ai\Agents\DesignAssistant;
use App\Ai\AssistantFailure;
use App\Ai\DesignSession;
use App\Enums\EmailStatus;
use App\Http\Requests\DesignAssistantRequest;
use App\Mcp\Support\ResourcePayload;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Services\AssistantModels;
use App\Services\EmailBuilderAgent;
use Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\StreamedEvent;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DesignAssistantController extends Controller
{
    public function __construct(
        private readonly AssistantModels $models,
        private readonly ResolveBuilderMergeTags $mergeTags,
        private readonly EmailBuilderAgent $builder,
        private readonly SearchReadyTeamMedia $media,
        private readonly ResourcePayload $payload,
        private readonly ConsumeAssistantRequest $quota,
    ) {}

    public function campaign(DesignAssistantRequest $request, Team $currentTeam, Email $email): StreamedResponse|JsonResponse
    {
        abort_if($email->status !== EmailStatus::Draft, 409, __('A queued campaign can no longer be edited.'));

        return $this->stream($request, $currentTeam, $email, $request->campaignBrief());
    }

    public function transactional(DesignAssistantRequest $request, Team $currentTeam, TransactionalEmail $transactionalEmail): StreamedResponse|JsonResponse
    {
        return $this->stream($request, $currentTeam, $transactionalEmail);
    }

    public function template(DesignAssistantRequest $request, Team $currentTeam, EmailTemplate $emailTemplate): StreamedResponse|JsonResponse
    {
        return $this->stream($request, $currentTeam, $emailTemplate);
    }

    /**
     * Streams one design assistant turn as server-sent events: `text` deltas,
     * `tool` progress, then a `proposal` with the ops for the editor to review,
     * and finally `done`. Nothing is saved; the author accepts the proposal.
     *
     * @param  array{name?: string, subject?: string, preheader?: string, audience?: string}|null  $campaign
     */
    private function stream(DesignAssistantRequest $request, Team $currentTeam, Email|TransactionalEmail|EmailTemplate $record, ?array $campaign = null): StreamedResponse|JsonResponse
    {
        $model = $this->models->find($request->validated('model'));
        abort_if($model === null, 422);

        $refusal = $this->quota->handle($currentTeam);

        if ($refusal !== null) {
            return response()->json(['message' => $refusal], 429);
        }

        $session = new DesignSession(
            $request->input('design'),
            $currentTeam,
            $this->mergeTags->handle($record),
            $record instanceof Email,
            $this->builder,
            $this->media,
            $this->payload,
        );
        $prompt = $request->validated('prompt');
        $history = $request->history();

        return response()->eventStream(function () use ($session, $prompt, $history, $model, $campaign): Generator {
            set_time_limit((int) config('assistant.run_timeout', 300));
            $maxReply = (int) config('assistant.max_reply_characters', 2000);
            $replyLength = 0;

            try {
                $stream = (new DesignAssistant($session, $history, $campaign))->stream(
                    $prompt,
                    provider: $model['provider'],
                    model: $model['model'],
                    timeout: (int) config('assistant.timeout', 120),
                );

                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        $replyLength += mb_strlen($event->delta);

                        // Design replies are a sentence or two; a long one means the
                        // assistant is being used as a general chatbot, so stop it.
                        if ($replyLength > $maxReply) {
                            yield new StreamedEvent(event: 'error', data: ['message' => __('The assistant only helps with this email\'s design and copy.')]);
                            yield new StreamedEvent(event: 'done', data: ['ok' => false]);

                            return;
                        }

                        yield new StreamedEvent(event: 'text', data: ['delta' => $event->delta]);
                    } elseif ($event instanceof ToolCall) {
                        yield new StreamedEvent(event: 'tool', data: [
                            'name' => $event->toolCall->name,
                            'detail' => $this->toolDetail($event->toolCall->name, $event->toolCall->arguments),
                        ]);
                    } elseif ($event instanceof Error) {
                        report(new \RuntimeException("Design assistant stream error: {$event->type} {$event->message}"));

                        yield new StreamedEvent(event: 'error', data: ['message' => __('The assistant ran into a problem. Try again.')]);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);

                yield new StreamedEvent(event: 'error', data: ['message' => AssistantFailure::describe($exception)]);
            }

            if ($session->ops() !== []) {
                yield new StreamedEvent(event: 'proposal', data: [
                    'ops' => $session->ops(),
                    'warnings' => $session->warnings(),
                ]);
            }

            yield new StreamedEvent(event: 'done', data: ['ok' => true]);
        }, endStreamWith: null);
    }

    /**
     * A short, plain-text hint for the activity list (a section name, block
     * id, search term or count), so the author can follow what changes.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function toolDetail(string $tool, array $arguments): ?string
    {
        $detail = match ($tool) {
            'insert_section' => $arguments['name'] ?? null,
            'get_block', 'update_block', 'move_block', 'remove_block', 'duplicate_block', 'replace_block' => $arguments['id'] ?? null,
            'list_media' => $arguments['search'] ?? null,
            'insert_blocks' => is_array($arguments['blocks'] ?? null) ? trans_choice(':count block|:count blocks', count($arguments['blocks'])) : null,
            'apply_ops' => is_array($arguments['ops'] ?? null) ? trans_choice(':count change|:count changes', count($arguments['ops'])) : null,
            default => null,
        };

        return is_string($detail) && $detail !== '' ? mb_strimwidth($detail, 0, 40, '…') : null;
    }
}
