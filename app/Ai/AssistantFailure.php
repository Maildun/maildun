<?php

namespace App\Ai;

use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * Turns an assistant failure into a message that says what to fix, without
 * exposing keys or raw provider responses to the browser.
 */
class AssistantFailure
{
    public static function describe(Throwable $exception): string
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $message = self::match($current);

            if ($message !== null) {
                return $message;
            }
        }

        return self::text('The assistant is unavailable right now. Try again or pick another model.');
    }

    private static function match(Throwable $exception): ?string
    {
        return match (true) {
            // Thrown by the email builder bridge; its messages are written for operators.
            $exception instanceof ValidationException => (string) collect($exception->errors())->flatten()->first(),
            $exception instanceof InsufficientCreditsException => self::text('The AI provider account is out of credit. Add credit or use another key.'),
            $exception instanceof RateLimitedException => self::rateLimited($exception),
            $exception instanceof ProviderOverloadedException => self::text('The AI provider is overloaded right now. Try again in a moment.'),
            $exception instanceof ProviderConnectionException => self::text('The server could not reach the AI provider. Check its outbound network access.'),
            $exception instanceof RequestException && $exception->response !== null => self::httpFailure($exception),
            default => null,
        };
    }

    private static function rateLimited(RateLimitedException $exception): string
    {
        $previous = $exception->getPrevious();

        // OpenAI reports an exhausted quota as a 429 too.
        if ($previous instanceof RequestException && $previous->response->json('error.code') === 'insufficient_quota') {
            return self::text('The AI provider account is out of credit. Add credit or use another key.');
        }

        return self::text('The AI provider is rate limiting requests. Wait a moment and try again.');
    }

    private static function httpFailure(RequestException $exception): ?string
    {
        $status = $exception->response->status();
        $code = (string) $exception->response->json('error.code', '');

        return match (true) {
            $status === 401, $status === 403, $code === 'invalid_api_key' => self::text('The AI provider rejected the API key. Check the key in the server environment.'),
            $status === 404, $code === 'model_not_found' => self::text('The configured model is not available for this API key. Check the assistant model settings.'),
            $status === 400 => self::text('The AI provider rejected the request. Try again or pick another model.'),
            default => null,
        };
    }

    private static function text(string $message): string
    {
        $translated = __($message);

        return is_string($translated) ? $translated : $message;
    }
}
