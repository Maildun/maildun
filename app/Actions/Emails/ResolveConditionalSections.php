<?php

namespace App\Actions\Emails;

use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\Tag;

/**
 * Personalized sections from @maildun/email-builder arrive wrapped in
 * markers, <!--meb:if:ID {"conditions":[…]}-->…<!--meb:endif:ID-->, which
 * only the sender can resolve because only it knows each recipient. A
 * condition targets a segment or tag; keep the sections this recipient
 * passes and drop the rest, matching the package's own resolveConditions().
 * Recipients without a subscriber (tests, transactional) are in no group.
 */
class ResolveConditionalSections
{
    public const string MARKER = '<!--meb:if:';

    /**
     * Where merge data carries the recipient's segment and tag ids. The "@"
     * keeps it from ever matching a {{ merge_tag }}.
     */
    public const string DATA_KEY = '@conditions';

    private const string PATTERN = '/<!--meb:if:([A-Za-z][\w.]*) (\{[^\n]*?\})-->(.*?)<!--meb:endif:\1-->/s';

    public static function segmentId(string $uuid): string
    {
        return 'segment:'.$uuid;
    }

    public static function tagId(string $uuid): string
    {
        return 'tag:'.$uuid;
    }

    /**
     * The condition ids of every segment and tag the subscriber is in, for
     * merge data under DATA_KEY. Eager load `segments` and `tags` when doing
     * this for many subscribers.
     *
     * @return list<string>
     */
    public static function memberships(Subscriber $subscriber): array
    {
        return [
            ...$subscriber->segments->map(fn (Segment $segment): string => self::segmentId($segment->uuid))->all(),
            ...$subscriber->tags->map(fn (Tag $tag): string => self::tagId($tag->uuid))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data  Merge data, with the recipient's memberships under DATA_KEY.
     */
    public function handle(string $content, array $data): string
    {
        if (! str_contains($content, self::MARKER)) {
            return $content;
        }

        $memberships = is_array($data[self::DATA_KEY] ?? null) ? $data[self::DATA_KEY] : [];

        return $this->resolve($content, array_fill_keys(array_filter($memberships, is_string(...)), true));
    }

    /**
     * @param  array<string, true>  $memberships
     */
    private function resolve(string $content, array $memberships): string
    {
        return (string) preg_replace_callback(self::PATTERN, function (array $matches) use ($memberships): string {
            $visibleIf = json_decode($matches[2], true);

            if (! is_array($visibleIf) || ! is_array($visibleIf['conditions'] ?? null) || ! $this->passes($visibleIf, $memberships)) {
                return '';
            }

            return $this->resolve($matches[3], $memberships);
        }, $content);
    }

    /**
     * @param  array{match?: mixed, conditions: array<mixed>}  $visibleIf
     * @param  array<string, true>  $memberships
     */
    private function passes(array $visibleIf, array $memberships): bool
    {
        if ($visibleIf['conditions'] === []) {
            return true;
        }

        $results = array_map(function (mixed $condition) use ($memberships): bool {
            if (! is_array($condition) || ! is_string($condition['id'] ?? null)) {
                return false;
            }

            return isset($memberships[$condition['id']]) !== (($condition['not'] ?? false) === true);
        }, $visibleIf['conditions']);

        return ($visibleIf['match'] ?? 'all') === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }
}
