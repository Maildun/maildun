<?php

use Laravel\Ai\Enums\Lab;

return [

    /*
    |--------------------------------------------------------------------------
    | Design Assistant Models
    |--------------------------------------------------------------------------
    |
    | The models people can pick in the block editor's design assistant, in
    | picker order. The first available one is the default. A model is only
    | offered when its provider has an API key in config/ai.php, so adding a
    | key (e.g. ANTHROPIC_API_KEY) is all it takes to unlock its models.
    |
    | "id" is what the browser sends back; keep it stable so remembered picks
    | survive a model upgrade. "provider" is a Laravel\Ai\Enums\Lab value.
    |
    */

    'models' => [
        [
            'id' => 'openai',
            'provider' => Lab::OpenAI->value,
            'model' => env('ASSISTANT_OPENAI_MODEL', 'gpt-6-luna'),
            'label' => 'OpenAI',
        ],
        [
            'id' => 'anthropic-default',
            'provider' => Lab::Anthropic->value,
            'model' => env('ASSISTANT_ANTHROPIC_MODEL', 'claude-sonnet-5'),
            'label' => 'Claude',
        ],
        [
            'id' => 'anthropic-fast',
            'provider' => Lab::Anthropic->value,
            'model' => env('ASSISTANT_ANTHROPIC_FAST_MODEL', 'claude-haiku-4-5-20251001'),
            'label' => 'Claude (fast)',
        ],
        [
            'id' => 'gemini-default',
            'provider' => Lab::Gemini->value,
            'model' => env('ASSISTANT_GEMINI_MODEL', 'gemini-3.6-flash'),
            'label' => 'Gemini',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | "daily_limit" is how many assistant requests one workspace may make per
    | rolling 24 hours (0 disables it); it is the real cost guard, since a
    | prompt can't fully stop people using the assistant as a general chatbot.
    | "max_reply_characters" stops a reply that runs past what a design change
    | needs, and "max_tokens" caps each model step, including tool calls that
    | write a whole email, so keep it generous.
    |
    | "max_steps" caps the tool calls one request may make, and "timeout" is
    | the HTTP timeout in seconds for each provider call. "run_timeout" is the
    | PHP time limit for a whole request: a run makes several provider calls,
    | so it outlasts PHP's default 30-second max_execution_time.
    |
    */

    'daily_limit' => (int) env('ASSISTANT_DAILY_LIMIT', 100),
    'max_reply_characters' => (int) env('ASSISTANT_MAX_REPLY_CHARACTERS', 2000),
    'max_tokens' => (int) env('ASSISTANT_MAX_TOKENS', 4096),
    'max_steps' => (int) env('ASSISTANT_MAX_STEPS', 12),
    'timeout' => (int) env('ASSISTANT_TIMEOUT', 120),
    'run_timeout' => (int) env('ASSISTANT_RUN_TIMEOUT', 300),

];
