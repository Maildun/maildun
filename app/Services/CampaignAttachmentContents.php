<?php

namespace App\Services;

use App\Models\EmailAttachment;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Campaign attachment bytes, read from storage once per worker instead of
 * once per recipient.
 *
 * A campaign sends the same files to every recipient, so re-reading them from
 * S3 for each delivery multiplies storage traffic by the audience size. The
 * cache lives as long as the queue worker and holds at most one campaign's
 * attachment allowance, evicting the oldest files first. An entry is keyed by
 * the stored path and size as well as the id, so a replaced file is never
 * served stale.
 */
#[Singleton]
class CampaignAttachmentContents
{
    /** @var array<string, string> */
    private array $contents = [];

    private int $cachedBytes = 0;

    public function get(EmailAttachment $attachment): string
    {
        $key = $attachment->id.':'.$attachment->path.':'.$attachment->size;

        if (array_key_exists($key, $this->contents)) {
            return $this->contents[$key];
        }

        $contents = Storage::disk($attachment->disk)->get($attachment->path);

        if ($contents === null) {
            throw new RuntimeException(__('A campaign attachment could not be read.'));
        }

        $this->remember($key, $contents);

        return $contents;
    }

    private function remember(string $key, string $contents): void
    {
        $limit = EmailAttachment::maxTotalBytes();
        $size = strlen($contents);

        if ($size > $limit) {
            return;
        }

        while ($this->cachedBytes + $size > $limit && $this->contents !== []) {
            $oldestKey = array_key_first($this->contents);
            $this->cachedBytes -= strlen($this->contents[$oldestKey]);
            unset($this->contents[$oldestKey]);
        }

        $this->contents[$key] = $contents;
        $this->cachedBytes += $size;
    }
}
