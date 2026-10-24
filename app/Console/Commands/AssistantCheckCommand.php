<?php

namespace App\Console\Commands;

use App\Ai\AssistantFailure;
use App\Services\AssistantModels;
use App\Services\EmailBuilderAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Ai\agent;

#[Signature('assistant:check {--model= : Model id from config/assistant.php to call (defaults to the first available)}')]
#[Description('Verify the design assistant setup: provider keys, the email builder runtime, and one model call')]
class AssistantCheckCommand extends Command
{
    public function handle(AssistantModels $models, EmailBuilderAgent $builder): int
    {
        $available = $models->available();

        if ($available === []) {
            $this->components->error('No assistant model is available. Set OPENAI_API_KEY (or another provider key) and clear the config cache.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Models', implode(', ', array_column($available, 'id')));

        $runtimeError = $builder->diagnose();

        if ($runtimeError !== null) {
            $this->components->twoColumnDetail('Email builder runtime', '<fg=red>failed</>');
            $this->components->error($runtimeError);
            $this->line('  Install Node.js 20+ (or Bun) for the PHP user, set MCP_EMAIL_BUILDER_RUNTIME to its absolute path,');
            $this->line('  make sure node_modules is installed in the deployed release, then clear the config cache.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Email builder runtime', '<fg=green>ready</> ('.config('mcp.email_builder_runtime').')');

        $model = $this->option('model') !== null ? $models->find((string) $this->option('model')) : $available[0];

        if ($model === null) {
            $this->components->error('Unknown or unavailable model id: '.$this->option('model'));

            return self::FAILURE;
        }

        try {
            agent(instructions: 'Reply with the single word OK.')->prompt(
                'Health check',
                provider: $model['provider'],
                model: $model['model'],
                timeout: 30,
            );
            $this->components->twoColumnDetail("{$model['label']} ({$model['model']})", '<fg=green>ready</>');
        } catch (Throwable $exception) {
            $this->components->twoColumnDetail("{$model['label']} ({$model['model']})", '<fg=red>failed</>');
            $this->components->error(AssistantFailure::describe($exception));
            $this->line('  '.class_basename($exception).': '.$this->sanitize($exception->getMessage()));

            return self::FAILURE;
        }

        $this->components->info('The design assistant is ready.');

        return self::SUCCESS;
    }

    private function sanitize(string $message): string
    {
        $keys = array_values(array_filter(
            array_map(fn (array $provider): mixed => $provider['key'] ?? null, (array) config('ai.providers', [])),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        return str_replace($keys, '[redacted]', mb_strimwidth($message, 0, 400, '…'));
    }
}
