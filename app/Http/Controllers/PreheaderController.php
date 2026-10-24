<?php

namespace App\Http\Controllers;

use App\Actions\Emails\ConsumeAssistantRequest;
use App\Ai\Agents\PreheaderWriter;
use App\Ai\AssistantFailure;
use App\Enums\EmailStatus;
use App\Http\Requests\GeneratePreheaderRequest;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Models\TransactionalEmail;
use App\Services\AssistantModels;
use App\Services\EmailBuilderAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

class PreheaderController extends Controller
{
    /** How much of the email's text the writer reads. */
    private const MAX_CONTENT_CHARACTERS = 6000;

    public function __construct(
        private readonly AssistantModels $models,
        private readonly EmailBuilderAgent $builder,
        private readonly ConsumeAssistantRequest $quota,
    ) {}

    public function campaign(GeneratePreheaderRequest $request, Team $currentTeam, Email $email): JsonResponse
    {
        abort_if($email->status !== EmailStatus::Draft, 409, __('A queued campaign can no longer be edited.'));

        return $this->generate($request, $currentTeam);
    }

    public function transactional(GeneratePreheaderRequest $request, Team $currentTeam, TransactionalEmail $transactionalEmail): JsonResponse
    {
        return $this->generate($request, $currentTeam);
    }

    public function template(GeneratePreheaderRequest $request, Team $currentTeam, EmailTemplate $emailTemplate): JsonResponse
    {
        return $this->generate($request, $currentTeam);
    }

    /**
     * Writes a preheader from the subject and the design as it is on screen,
     * saved or not. Nothing is stored; the editor fills the field.
     */
    private function generate(GeneratePreheaderRequest $request, Team $currentTeam): JsonResponse
    {
        $model = filled($request->validated('model'))
            ? $this->models->find($request->validated('model'))
            : ($this->models->available()[0] ?? null);
        abort_if($model === null, 422);

        // The current preview text is left out, so the writer starts fresh.
        $design = $request->input('design') ?? [];
        Arr::forget($design, 'settings.preheader');

        try {
            $content = trim($this->builder->render($design)['text']);
        } catch (ValidationException $exception) {
            return response()->json(['message' => AssistantFailure::describe($exception)], 422);
        }

        if ($content === '') {
            return response()->json(['message' => __('Add some content to the email first.')], 422);
        }

        $refusal = $this->quota->handle($currentTeam);

        if ($refusal !== null) {
            return response()->json(['message' => $refusal], 429);
        }

        $subject = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) $request->validated('subject')));
        $prompt = ($subject !== '' ? "Subject: {$subject}\n\n" : '')
            ."Email text:\n".mb_strimwidth($content, 0, self::MAX_CONTENT_CHARACTERS, '…');

        try {
            $response = (new PreheaderWriter)->prompt(
                $prompt,
                provider: $model['provider'],
                model: $model['model'],
                timeout: (int) config('assistant.timeout', 120),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => AssistantFailure::describe($exception)], 502);
        }

        $written = $response['preheader'] ?? '';
        $preheader = trim(preg_replace('/\s+/u', ' ', is_string($written) ? $written : '') ?? '');

        if ($preheader === '') {
            return response()->json(['message' => __('The assistant could not write a preheader. Try again.')], 502);
        }

        return response()->json(['preheader' => mb_strimwidth($preheader, 0, 255, '…')]);
    }
}
