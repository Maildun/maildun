<?php

namespace App\Services;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use JsonException;

class EmailBuilderAgent
{
    /**
     * @param  array<string, mixed>|null  $document
     * @param  array<string, mixed>  $input
     * @param  list<string>  $mergeTags
     * @return array<string, mixed>
     */
    public function run(?array $document, ?string $html, string $tool, array $input = [], array $mergeTags = []): array
    {
        $payload = json_encode([
            'document' => $document,
            'html' => $html,
            'tool' => $tool,
            'input' => (object) $input,
            'merge_tags' => $mergeTags,
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

        if (isset($result['tools'])) {
            // Keep JSON Schema objects as objects; associative decoding re-encodes `{}` as invalid `[]`.
            $result['tools'] = json_decode($process->output(), flags: JSON_THROW_ON_ERROR)->tools;
        }

        return $result;
    }
}
