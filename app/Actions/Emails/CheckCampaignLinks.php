<?php

namespace App\Actions\Emails;

use App\Models\Email;
use App\Services\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckCampaignLinks
{
    public function __construct(
        private readonly BuildTrackedEmailHtml $trackedHtml,
        private readonly PublicHttpUrlGuard $urlGuard,
    ) {}

    /**
     * @return array{checked: int, broken: list<array{url: string, status: int|null, reason: string}>}
     */
    public function handle(Email $email): array
    {
        $urls = $this->trackedHtml->extractLinks($email->html ?? '', $email->query_string);
        $broken = [];

        foreach ($urls as $url) {
            try {
                $target = $this->urlGuard->ensurePublic($url);
                $address = str_contains($target['address'], ':') ? '['.$target['address'].']' : $target['address'];
                $response = Http::connectTimeout(3)
                    ->timeout(5)
                    ->withoutRedirecting()
                    ->withOptions([
                        'curl' => [
                            CURLOPT_RESOLVE => [
                                $target['hostname'].':'.$target['port'].':'.$address,
                            ],
                        ],
                    ])
                    ->head($url);

                if ($response->status() >= 400) {
                    $broken[] = [
                        'url' => $url,
                        'status' => $response->status(),
                        'reason' => __('HTTP :status', ['status' => $response->status()]),
                    ];
                }
            } catch (Throwable) {
                $broken[] = [
                    'url' => $url,
                    'status' => null,
                    'reason' => __('Could not reach this public URL.'),
                ];
            }
        }

        return ['checked' => count($urls), 'broken' => $broken];
    }
}
