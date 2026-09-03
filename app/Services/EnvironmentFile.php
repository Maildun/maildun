<?php

namespace App\Services;

use Illuminate\Contracts\Foundation\Application;

/**
 * Reads and rewrites the deployment's .env file.
 *
 * Install-time commands need to persist a handful of keys and then keep
 * working in the same process, which .env alone cannot do because it is only
 * parsed at boot. Laravel Cloud also serves .env from its dashboard rather
 * than the filesystem, so every write has to cope with an unwritable file.
 */
class EnvironmentFile
{
    public function __construct(private Application $app) {}

    public function path(): string
    {
        return $this->app->environmentFilePath();
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * Seed .env from .env.example when the deployment has no file yet.
     *
     * @return bool Whether a file was created by this call.
     */
    public function ensureExists(): bool
    {
        if ($this->exists()) {
            return false;
        }

        $example = base_path('.env.example');

        return is_file($example) && copy($example, $this->path());
    }

    /**
     * Determine whether values can be persisted to disk at all.
     *
     * A missing file counts as writable when its directory is, because put()
     * creates it. Laravel Cloud mounts the environment read-only, and there the
     * operator sets values in the dashboard instead.
     */
    public function isWritable(): bool
    {
        return $this->exists()
            ? is_writable($this->path())
            : is_writable(dirname($this->path()));
    }

    /**
     * Read a value from the current process environment.
     */
    public function get(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? $value : $default;
    }

    /**
     * Replace each key in place, appending the ones that are not present yet.
     *
     * @param  array<string, string>  $values
     */
    public function put(array $values): void
    {
        $this->ensureExists();

        $contents = $this->exists() ? (string) file_get_contents($this->path()) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->encode($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace($pattern, addcslashes($line, '\\$'), $contents, 1)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        file_put_contents($this->path(), $contents);
    }

    /**
     * Mirror values into the running process so config re-reads see them.
     *
     * @param  array<string, string>  $values
     */
    public function applyToRuntime(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    /**
     * Render a value so dotenv reads back exactly what was written.
     */
    public function encode(string $value): string
    {
        return preg_match('/[\s#"\']/', $value) === 1
            ? '"'.str_replace('"', '\"', $value).'"'
            : $value;
    }
}
