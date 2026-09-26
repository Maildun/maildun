<?php

namespace App\Actions\Emails;

use App\Models\Email;
use App\Services\PublicHttpUrlGuard;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckCampaignLinks
{
    public function __construct(
        private readonly BuildTrackedEmailHtml $trackedHtml,
        private readonly PublicHttpUrlGuard $urlGuard,
    ) {}

    /**
     * Redirects are followed by hand so every hop is resolved and checked by
     * PublicHttpUrlGuard before it is requested; a redirect can never reach a
     * private address. Past this many hops the link is reported as broken.
     */
    private const int MAX_REDIRECTS = 5;

    /**
     * @return array{checked: int, broken: list<array{url: string, status: int|null, reason: string}>}
     */
    public function handle(Email $email): array
    {
        $urls = $this->trackedHtml->extractLinks($email->html ?? '', $email->query_string);
        $broken = [];

        foreach ($urls as $url) {
            $problem = $this->check($url);

            if ($problem !== null) {
                $broken[] = $problem;
            }
        }

        return ['checked' => count($urls), 'broken' => $broken];
    }

    /** @return array{url: string, status: int|null, reason: string}|null */
    private function check(string $url): ?array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            try {
                $response = $this->request($current, 'head');

                // Some servers refuse HEAD; ask again with a GET that does not
                // download the body.
                if (in_array($response->status(), [405, 501], true)) {
                    $response = $this->request($current, 'get');
                }
            } catch (Throwable) {
                return $this->broken($url, null, $hop === 0
                    ? __('Could not reach this public URL.')
                    : __('Redirects to :target, which could not be reached.', ['target' => $current]));
            }

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === '') {
                    return $this->broken($url, $response->status(), __('Redirects without saying where to.'));
                }

                $current = (string) UriResolver::resolve(new Uri($current), new Uri($location));

                continue;
            }

            if ($response->status() >= 400) {
                return $this->broken($url, $response->status(), $hop === 0
                    ? __('HTTP :status', ['status' => $response->status()])
                    : __('Redirects to :target, which returns HTTP :status.', ['target' => $current, 'status' => $response->status()]));
            }

            return null;
        }

        return $this->broken($url, null, __('Redirects more than :count times.', ['count' => self::MAX_REDIRECTS]));
    }

    private function request(string $url, string $method): Response
    {
        $target = $this->urlGuard->ensurePublic($url);
        $address = str_contains($target['address'], ':') ? '['.$target['address'].']' : $target['address'];

        return Http::connectTimeout(3)
            ->timeout(5)
            ->withoutRedirecting()
            ->withOptions([
                'stream' => $method === 'get',
                'curl' => [
                    CURLOPT_RESOLVE => [
                        $target['hostname'].':'.$target['port'].':'.$address,
                    ],
                ],
            ])
            ->send(strtoupper($method), $url);
    }

    /** @return array{url: string, status: int|null, reason: string} */
    private function broken(string $url, ?int $status, string $reason): array
    {
        return ['url' => $url, 'status' => $status, 'reason' => $reason];
    }
}
