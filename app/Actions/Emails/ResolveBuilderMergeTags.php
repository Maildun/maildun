<?php

namespace App\Actions\Emails;

use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\TransactionalEmail;

class ResolveBuilderMergeTags
{
    /**
     * Tags every campaign can use, before audience-specific fields.
     *
     * @var list<string>
     */
    public const array CAMPAIGN_TAGS = [
        'first_name', 'last_name', 'name', 'email', 'language', 'language_name', 'web_view_url', 'unsubscribe_url', 'subscribe_url',
        'day', 'day_name', 'month', 'month_name', 'year',
    ];

    /**
     * The merge tags an author may place in a block email, so agents only use
     * tags the sending path will actually fill.
     *
     * @return list<string>
     */
    public function handle(Email|TransactionalEmail|EmailTemplate $record): array
    {
        $tags = match (true) {
            $record instanceof Email => [
                ...self::CAMPAIGN_TAGS,
                ...($record->audience?->audienceAttributes()->pluck('key')->all() ?? []),
            ],
            $record instanceof TransactionalEmail => ['web_view_url', ...array_column($record->variables, 'key')],
            default => self::CAMPAIGN_TAGS,
        };

        return array_values(array_unique(array_filter($tags, is_string(...))));
    }
}
