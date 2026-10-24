<?php

namespace App\Ai;

use App\Actions\Media\SearchReadyTeamMedia;
use App\Ai\Tools\InsertSavedSection;
use App\Ai\Tools\ListDesignMedia;
use App\Ai\Tools\ListSavedSections;
use App\Ai\Tools\NativeDesignTool;
use App\Mcp\Support\ResourcePayload;
use App\Models\Team;
use App\Services\EmailBuilderAgent;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;

/**
 * The design assistant's working copy of an email. Tools edit this in-memory
 * document through the email builder package; nothing is saved. The ops it
 * collects replay in the browser as a proposal the author accepts or rejects.
 */
class DesignSession
{
    /** @var list<mixed> */
    private array $ops = [];

    /** @var list<array<string, mixed>> */
    private array $warnings = [];

    /**
     * @param  array<string, mixed>  $document
     * @param  list<string>  $mergeTags
     */
    public function __construct(
        private array $document,
        private readonly Team $team,
        private readonly array $mergeTags,
        private readonly bool $requireUnsubscribe,
        private readonly EmailBuilderAgent $agent,
        private readonly SearchReadyTeamMedia $media,
        private readonly ResourcePayload $payload,
    ) {}

    /**
     * Runs one native builder tool against the working document. Rejected
     * calls come back as text so the model can read the issues and retry.
     *
     * @param  array<string, mixed>  $input
     */
    public function run(string $tool, array $input = []): string
    {
        try {
            $result = $this->agent->run($this->document, null, $tool, $input, $this->mergeTags, $this->requireUnsubscribe);
        } catch (ValidationException $exception) {
            return implode("\n", $exception->validator->errors()->all());
        }

        $this->document = $result['document'];
        $this->warnings = $result['warnings'] ?? [];
        array_push($this->ops, ...($result['ops'] ?? []));
        $content = (string) ($result['result']['content'] ?? '');

        if (in_array($tool, ['get_document', 'get_block', 'check_email'], true)) {
            return $content;
        }

        return $content."\n\nOutline now:\n".$result['outline'];
    }

    /**
     * The package's agent reference (block catalog, sections, rules) for this
     * email's merge tags, plus the current outline.
     */
    public function reference(): string
    {
        $result = $this->agent->run($this->document, null, 'get_document', [], $this->mergeTags, $this->requireUnsubscribe);

        return $result['instructions']."\n\n## Current email\n".$result['outline'];
    }

    /** @return list<Tool> */
    public function tools(): array
    {
        return [
            ...NativeDesignTool::all($this),
            new ListDesignMedia($this->team, $this->media, $this->payload),
            new ListSavedSections($this->team),
            new InsertSavedSection($this, $this->team),
        ];
    }

    /**
     * Every op applied in this session, with explicit ids so the browser
     * replays them to exactly the same document.
     *
     * @return list<mixed>
     */
    public function ops(): array
    {
        return $this->ops;
    }

    /** @return list<array<string, mixed>> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function requiresUnsubscribe(): bool
    {
        return $this->requireUnsubscribe;
    }
}
