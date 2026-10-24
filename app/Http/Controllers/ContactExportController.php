<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersCampaignRecipients;
use App\Concerns\ReadsQueryFilters;
use App\Concerns\SanitizesSpreadsheetCells;
use App\Concerns\SearchesText;
use App\Enums\EmailStatus;
use App\Enums\SubscriberLanguage;
use App\Enums\SubscriberSource;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Contact;
use App\Models\Email;
use App\Models\EmailDelivery;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    use FiltersCampaignRecipients;
    use ReadsQueryFilters;
    use SanitizesSpreadsheetCells;
    use SearchesText;

    public function contacts(Request $request, Team $currentTeam, string $format): StreamedResponse
    {
        Gate::authorize('create', [Contact::class, $currentTeam]);

        $filters = $this->contactFilters($request, $currentTeam);
        $query = Contact::query()
            ->whereBelongsTo($currentTeam)
            ->with([
                'company:id,name',
                'tags:id,name',
                'subscribers.audience:id,name',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $this->wherePersonContains($query, $filters['search']);
            })
            ->when($filters['tag'] !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $tagQuery) => $tagQuery->where('tags.uuid', $filters['tag']),
            ))
            ->when($filters['company'] === 'assigned', fn (Builder $query) => $query->whereNotNull('company_id'))
            ->when($filters['company'] === 'unassigned', fn (Builder $query) => $query->whereNull('company_id'))
            ->when(
                $filters['company'] !== '' && ! in_array($filters['company'], ['assigned', 'unassigned'], true),
                fn (Builder $query) => $query->whereHas(
                    'company',
                    fn (Builder $companyQuery) => $companyQuery->where('uuid', $filters['company']),
                ),
            )
            // Audience and language must hold on the same membership.
            ->when($filters['audience'] !== '' || $filters['language'] !== '', fn (Builder $query) => $query->whereHas(
                'subscribers',
                fn (Builder $subscriberQuery) => $subscriberQuery
                    ->when($filters['audience'] !== '', fn (Builder $membership) => $membership->whereHas(
                        'audience',
                        fn (Builder $audienceQuery) => $audienceQuery->where('uuid', $filters['audience']),
                    ))
                    ->when($filters['language'] !== '', fn (Builder $membership) => $membership->where('language', $filters['language'])),
            ));

        // Follow the list's sort. Name order is not unique per row, so it
        // pages by offset with the id as the tiebreaker instead of by id.
        $contacts = match ($filters['sort']) {
            'oldest' => $query->lazyById(500),
            'name' => $query->orderByName()->lazy(500),
            default => $query->lazyByIdDesc(500),
        };

        return $this->download(
            $format,
            'contacts-'.now()->format('Y-m-d'),
            ['Email', 'First name', 'Last name', 'Company', 'Tags', 'Audiences', 'Languages', 'Created at'],
            $contacts,
            fn (Contact $contact): array => [
                $contact->email,
                $contact->first_name,
                $contact->last_name,
                $contact->company?->name,
                $contact->tags->pluck('name')->implode(', '),
                $contact->subscribers
                    ->map(fn (Subscriber $subscriber): string => $subscriber->audience->name.' ('.$this->membershipStatus($subscriber).')')
                    ->implode(', '),
                $contact->subscribers->map(fn (Subscriber $subscriber): ?string => $subscriber->language?->value)->filter()->unique()->implode(', '),
                $contact->created_at?->toIso8601String(),
            ],
        );
    }

    public function audience(
        Request $request,
        Team $currentTeam,
        Audience $audience,
        string $format,
    ): StreamedResponse {
        Gate::authorize('update', $audience);

        $filters = $this->subscriberFilters($request);
        $query = Subscriber::query()
            ->whereBelongsTo($audience)
            ->with('tags:id,name')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $this->wherePersonContains($query, $filters['search']);
            })
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->withMembershipStatus($filters['status']))
            ->when($filters['source'] !== 'all', fn (Builder $query) => $query->where('source', $filters['source']))
            ->when($filters['language'] !== 'all', fn (Builder $query) => $query->where('language', $filters['language']))
            ->when($filters['tag'] !== 'all', fn (Builder $query) => $query->whereHas(
                'contact.tags',
                fn (Builder $tagQuery) => $tagQuery->where('tags.uuid', $filters['tag']),
            ));

        // Custom fields are headed by their key, which is what an import maps
        // back to the field, so an exported file can be imported again.
        $attributeKeys = $audience->audienceAttributes()->orderBy('position')->get(['key'])
            ->map(fn (AudienceAttribute $attribute): string => $attribute->key)
            ->values()
            ->all();

        return $this->download(
            $format,
            (Str::slug($audience->name) ?: 'audience').'-contacts-'.now()->format('Y-m-d'),
            [
                'Email', 'First name', 'Last name', 'Status', 'Source', 'Language', 'Tags', 'Subscribed at',
                'Unsubscribed at', 'Consent text', 'Consented at', 'Consent IP',
                ...$attributeKeys,
            ],
            $query->lazyById(500),
            fn (Subscriber $subscriber): array => [
                $subscriber->email,
                $subscriber->first_name,
                $subscriber->last_name,
                $subscriber->status->value,
                $subscriber->source->value,
                $subscriber->language?->value,
                $subscriber->tags->pluck('name')->implode(', '),
                $subscriber->subscribed_at?->toIso8601String(),
                $subscriber->unsubscribed_at?->toIso8601String(),
                $subscriber->consent_text,
                $subscriber->consented_at?->toIso8601String(),
                $subscriber->consent_ip,
                ...array_map(
                    fn (string $key): ?string => $this->attributeCell($subscriber->attribute_values[$key] ?? null),
                    $attributeKeys,
                ),
            ],
        );
    }

    /**
     * One row per recipient of a sent campaign, following the report's status
     * tab and search. Managers only: the list is personal data.
     */
    public function campaignRecipients(
        Request $request,
        Team $currentTeam,
        Email $email,
        string $format,
    ): StreamedResponse {
        Gate::authorize('update', $email);
        abort_if($email->status === EmailStatus::Draft, 404);

        $query = $email->deliveries()->getQuery();
        $this->applyRecipientFilter($query, $this->recipientFilter($request), $currentTeam);
        $this->applyRecipientSearch($query, $this->recipientSearch($request));

        return $this->download(
            $format,
            (Str::slug($email->name) ?: 'campaign').'-recipients-'.now()->format('Y-m-d'),
            ['Email', 'First name', 'Last name', 'Status', 'Failure', 'Opens', 'Clicks', 'Sent at', 'Delivered at'],
            $query->lazyById(500),
            fn (EmailDelivery $delivery): array => [
                $delivery->email_address,
                $delivery->first_name,
                $delivery->last_name,
                $delivery->status->value,
                $delivery->failure_reason,
                (string) $delivery->opens_count,
                (string) $delivery->clicks_count,
                $delivery->sent_at?->toIso8601String(),
                $delivery->delivered_at?->toIso8601String(),
            ],
        );
    }

    /**
     * @template TModel of Contact|Subscriber|EmailDelivery
     *
     * @param  list<string>  $headers
     * @param  LazyCollection<int, TModel>  $models
     * @param  callable(TModel): list<string|null>  $row
     */
    private function download(
        string $format,
        string $filename,
        array $headers,
        LazyCollection $models,
        callable $row,
    ): StreamedResponse {
        abort_unless(in_array($format, ['csv', 'xls'], true), 404);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $models, $row): void {
                $output = fopen('php://output', 'wb');

                if ($output === false) {
                    return;
                }

                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $this->sanitizeCsvRow($headers), ',', '"', '');

                foreach ($models as $model) {
                    fputcsv($output, $this->sanitizeCsvRow($row($model)), ',', '"', '');
                }

                fclose($output);
            }, $filename.'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $models, $row): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Contacts"><Table>';
            $this->writeSpreadsheetRow($headers);

            foreach ($models as $model) {
                $this->writeSpreadsheetRow($row($model));
            }

            echo '</Table></Worksheet></Workbook>';
        }, $filename.'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /** @param list<string|null> $cells */
    private function writeSpreadsheetRow(array $cells): void
    {
        echo '<Row>';

        foreach ($cells as $cell) {
            echo '<Cell><Data ss:Type="String">'
                .$this->spreadsheetXmlText($cell)
                .'</Data></Cell>';
        }

        echo '</Row>';
    }

    /**
     * The membership state shown in the contacts export's Audiences column.
     */
    private function membershipStatus(Subscriber $subscriber): string
    {
        return $subscriber->isPendingConfirmation()
            ? 'pending confirmation'
            : $subscriber->status->value;
    }

    /**
     * Attribute values are usually text, but the API can store numbers or
     * nested JSON.
     */
    private function attributeCell(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }

    /** @return array{search: string, company: string, audience: string, tag: string, language: string, sort: string} */
    private function contactFilters(Request $request, Team $team): array
    {
        $company = $this->queryFilter($request, 'company');
        $company = Str::isUuid($company) && ! $team->companies()->where('uuid', $company)->exists() ? '' : $company;
        $audience = $this->queryFilter($request, 'audience');
        $tag = $this->queryFilter($request, 'tag');
        $sort = $this->queryFilter($request, 'sort');

        return [
            'search' => $this->queryFilter($request, 'search'),
            'company' => in_array($company, ['assigned', 'unassigned'], true) || Str::isUuid($company)
                ? $company
                : '',
            'audience' => Str::isUuid($audience) ? $audience : '',
            'tag' => Str::isUuid($tag) ? Str::lower($tag) : '',
            'language' => SubscriberLanguage::tryFrom($this->queryFilter($request, 'language'))->value ?? '',
            'sort' => in_array($sort, ['oldest', 'name'], true) ? $sort : 'newest',
        ];
    }

    /** @return array{search: string, status: string, source: string, language: string, tag: string} */
    private function subscriberFilters(Request $request): array
    {
        $status = $this->queryFilter($request, 'status');
        $source = $this->queryFilter($request, 'source');
        $tag = $this->queryFilter($request, 'tag');

        return [
            'search' => $this->queryFilter($request, 'search'),
            'status' => in_array($status, Subscriber::MEMBERSHIP_STATUSES, true) ? $status : 'all',
            'source' => SubscriberSource::tryFrom($source)->value ?? 'all',
            'language' => SubscriberLanguage::tryFrom($this->queryFilter($request, 'language'))->value ?? 'all',
            'tag' => Str::isUuid($tag) ? Str::lower($tag) : 'all',
        ];
    }
}
