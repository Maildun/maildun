<?php

namespace App\Services;

use App\Casts\DesignDocument;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class EmailBuilderAgent
{
    /**
     * Why the runtime cannot run the bridge, for operators: null when it works,
     * otherwise the runtime's own error (missing binary, missing package, …).
     * Never shown to end users, since it can include server paths.
     */
    public function diagnose(): ?string
    {
        $runtime = (string) config('mcp.email_builder_runtime', 'node');

        try {
            $process = Process::path(base_path())
                ->timeout(20)
                ->input((string) json_encode(['document' => null, 'html' => null, 'tool' => 'get_document', 'input' => (object) [], 'merge_tags' => []]))
                ->run([$runtime, resource_path('js/lib/email-builder-agent.mjs')]);
        } catch (Throwable $exception) {
            return "Could not start \"{$runtime}\": ".$exception->getMessage();
        }

        if ($process->successful() && str_contains($process->output(), '"ok":true')) {
            return null;
        }

        $error = trim($process->errorOutput()) ?: trim($process->output()) ?: 'no output';

        return "\"{$runtime}\" exited with code {$process->exitCode()}: ".mb_strimwidth($error, 0, 600, '…');
    }

    /**
     * Validate a whole design supplied by a client and render its delivery HTML,
     * so the editor never receives a document the package cannot open.
     *
     * @param  array<string, mixed>  $design
     * @return array{design: array<string, mixed>, html: string, text: string}
     */
    public function render(array $design): array
    {
        $result = $this->run($design, null, 'check_email');

        return ['design' => $result['document'], 'html' => $result['html'], 'text' => $result['text']];
    }

    /**
     * @param  array<string, mixed>|null  $document
     * @param  array<string, mixed>  $input
     * @param  list<string>  $mergeTags
     * @param  bool  $requireUnsubscribe  Lint for a missing {{ unsubscribe_url }} link, as marketing email needs one.
     * @return array<string, mixed>
     */
    public function run(?array $document, ?string $html, string $tool, array $input = [], array $mergeTags = [], bool $requireUnsubscribe = false): array
    {
        $payload = json_encode([
            'document' => $document,
            'html' => $html,
            'tool' => $tool,
            'input' => (object) $input,
            'merge_tags' => $mergeTags,
            'require_unsubscribe' => $requireUnsubscribe,
        ], JSON_THROW_ON_ERROR);

        if (strlen($payload) > 4_000_000) {
            throw ValidationException::withMessages(['input' => 'The email design and operation are too large.']);
        }

        try {
            $process = Process::path(base_path())
                ->timeout(20)
                ->input($payload)
                ->run([
                    (string) config('mcp.email_builder_runtime', 'node'),
                    resource_path('js/lib/email-builder-agent.mjs'),
                ]);
        } catch (ProcessTimedOutException) {
            throw ValidationException::withMessages(['design' => 'The email builder timed out. Try a smaller edit.']);
        }

        if ($process->failed()) {
            throw ValidationException::withMessages([
                'design' => 'The email builder runtime is unavailable. Check MCP_EMAIL_BUILDER_RUNTIME and install JavaScript dependencies.',
            ]);
        }

        try {
            $result = json_decode($process->output(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['design' => 'The email builder returned an invalid response.']);
        }

        if (! is_array($result) || ($result['ok'] ?? false) !== true) {
            $message = is_array($result) ? ($result['result']['content'] ?? 'The email design is invalid.') : 'The email design is invalid.';

            throw ValidationException::withMessages(['design' => (string) $message]);
        }

        if (! is_array($result['document'] ?? null) || ! is_string($result['html'] ?? null) || ! is_string($result['text'] ?? null)) {
            throw ValidationException::withMessages(['design' => 'The email builder returned an invalid response.']);
        }

        if (strlen(json_encode($result['document'], JSON_THROW_ON_ERROR)) > 2_000_000 || strlen($result['html']) > 2_000_000) {
            throw ValidationException::withMessages(['design' => 'The resulting email is too large.']);
        }

        // Keep JSON objects as objects; associative decoding re-encodes `{}` as `[]`,
        // which breaks JSON Schemas, ops replayed in the browser editor, and the
        // `props: {}` of a saved or returned container.
        $objects = json_decode($process->output(), flags: JSON_THROW_ON_ERROR);
        $result['document'] = DesignDocument::fromObjects($objects->document);
        $result['result'] = DesignDocument::fromObjects($objects->result ?? null);

        foreach (['tools', 'ops'] as $key) {
            if (isset($result[$key])) {
                $result[$key] = $objects->{$key};
            }
        }

        return $result;
    }
}
