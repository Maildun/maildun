<?php

namespace App\Http\Controllers;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Audience;
use App\Models\Contact;
use App\Models\Subscriber;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function contacts(Request $request, Team $currentTeam, string $format): StreamedResponse
    {
        Gate::authorize('create', [Contact::class, $currentTeam]);

        $filters = $this->contactFilters($request);
        $query = Contact::query()
            ->whereBelongsTo($currentTeam)
            ->with([
                'company:id,name',
                'tags:id,name',
                'subscribers.audience:id,name',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.mb_strtolower($filters['search']).'%';
                $query->where(fn (Builder $contactQuery) => $contactQuery
                    ->whereRaw('LOWER(email) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$search]));
            })
            ->when($filters['company'] === 'assigned', fn (Builder $query) => $query->whereNotNull('company_id'))
            ->when($filters['company'] === 'unassigned', fn (Builder $query) => $query->whereNull('company_id'))
            ->when(
                $filters['company'] !== '' && ! in_array($filters['company'], ['assigned', 'unassigned'], true),
                fn (Builder $query) => $query->whereHas(
                    'company',
                    fn (Builder $companyQuery) => $companyQuery->where('uuid', $filters['company']),
                ),
            )
            ->when($filters['audience'] !== '', fn (Builder $query) => $query->whereHas(
                'subscribers.audience',
                fn (Builder $audienceQuery) => $audienceQuery->where('uuid', $filters['audience']),
            ));

        return $this->download(
            $format,
            'contacts-'.now()->format('Y-m-d'),
            ['Email', 'First name', 'Last name', 'Company', 'Tags', 'Audiences', 'Created at'],
            $query,
            fn (Contact $contact): array => [
                $contact->email,
                $contact->first_name,
                $contact->last_name,
                $contact->company?->name,
                $contact->tags->pluck('name')->implode(', '),
                $contact->subscribers->pluck('audience.name')->unique()->implode(', '),
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
                $search = '%'.mb_strtolower($filters['search']).'%';
                $query->where(fn (Builder $subscriberQuery) => $subscriberQuery
                    ->whereRaw('LOWER(email) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$search]));
            })
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['source'] !== 'all', fn (Builder $query) => $query->where('source', $filters['source']));

        return $this->download(
            $format,
            (Str::slug($audience->name) ?: 'audience').'-contacts-'.now()->format('Y-m-d'),
            ['Email', 'First name', 'Last name', 'Status', 'Source', 'Tags', 'Subscribed at'],
            $query,
            fn (Subscriber $subscriber): array => [
                $subscriber->email,
                $subscriber->first_name,
                $subscriber->last_name,
                $subscriber->status->value,
                $subscriber->source->value,
                $subscriber->tags->pluck('name')->implode(', '),
                $subscriber->subscribed_at?->toIso8601String(),
            ],
        );
    }

    /**
     * @template TModel of Contact|Subscriber
     *
     * @param  list<string>  $headers
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): list<string|null>  $row
     */
    private function download(
        string $format,
        string $filename,
        array $headers,
        Builder $query,
        callable $row,
    ): StreamedResponse {
        abort_unless(in_array($format, ['csv', 'xls'], true), 404);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $query, $row): void {
                $output = fopen('php://output', 'wb');

                if ($output === false) {
                    return;
                }

                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $this->sanitizeCsvRow($headers), ',', '"', '');

                foreach ($query->lazyById(500) as $model) {
                    fputcsv($output, $this->sanitizeCsvRow($row($model)), ',', '"', '');
                }

                fclose($output);
            }, $filename.'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $query, $row): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Contacts"><Table>';
            $this->writeSpreadsheetRow($headers);

            foreach ($query->lazyById(500) as $model) {
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
                .htmlspecialchars((string) $cell, ENT_QUOTES | ENT_XML1, 'UTF-8')
                .'</Data></Cell>';
        }

        echo '</Row>';
    }

    /**
     * @param  list<string|null>  $cells
     * @return list<string>
     */
    private function sanitizeCsvRow(array $cells): array
    {
        return array_map(function (?string $cell): string {
            $value = (string) $cell;

            return preg_match('/^[=+\-@\t\r]/u', $value) === 1
                ? "'".$value
                : $value;
        }, $cells);
    }

    /** @return array{search: string, company: string, audience: string} */
    private function contactFilters(Request $request): array
    {
        $company = $request->string('company')->toString();
        $audience = $request->string('audience')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'company' => in_array($company, ['assigned', 'unassigned'], true) || Str::isUuid($company)
                ? $company
                : '',
            'audience' => Str::isUuid($audience) ? $audience : '',
        ];
    }

    /** @return array{search: string, status: string, source: string} */
    private function subscriberFilters(Request $request): array
    {
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'status' => SubscriberStatus::tryFrom($status)->value ?? 'all',
            'source' => SubscriberSource::tryFrom($source)->value ?? 'all',
        ];
    }
}
