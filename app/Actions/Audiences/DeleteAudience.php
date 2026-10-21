<?php

namespace App\Actions\Audiences;

use App\Enums\AutomationStatus;
use App\Enums\EmailStatus;
use App\Enums\StorageBackend;
use App\Models\Audience;
use App\Models\Automation;
use App\Models\ContactImport;
use App\Models\Email;
use App\Models\SubscribeForm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteAudience
{
    /**
     * Delete the audience unless a scheduled or sending campaign still targets it.
     *
     * A campaign loads its recipients from the audience while it sends, and
     * emails.audience_id is nulled on delete, so removing it mid-send crashes the
     * send. Active automations triggered by this audience can never run again,
     * so they are paused rather than left looking active.
     *
     * Its imports and subscribe forms go with it through cascading foreign
     * keys, which fire no model events, so their uploaded CSVs, form artwork
     * and logos are collected first and removed once the delete commits.
     *
     * @return list<string> Names of the automations that were paused.
     *
     * @throws ValidationException
     */
    public function handle(Audience $audience, string $errorKey = 'name'): array
    {
        $campaigns = Email::query()
            ->where('audience_id', $audience->id)
            ->where(fn ($query) => $query
                ->whereIn('status', EmailStatus::active())
                ->orWhere(fn ($scheduled) => $scheduled->where('status', EmailStatus::Draft)->whereNotNull('scheduled_at')))
            ->orderBy('name')
            ->pluck('name');

        if ($campaigns->isNotEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => __('This audience is used by scheduled or sending campaigns: :campaigns.', [
                    'campaigns' => $campaigns->join(', '),
                ]),
            ]);
        }

        [$automations, $files] = DB::transaction(function () use ($audience): array {
            $automations = Automation::query()
                ->where('team_id', $audience->team_id)
                ->where('status', AutomationStatus::Active)
                ->where('trigger_config->audience_uuid', $audience->uuid)
                ->orderBy('name')
                ->get();

            Automation::query()
                ->whereKey($automations->modelKeys())
                ->update(['status' => AutomationStatus::Paused]);

            $files = $this->filesOnlyThisAudienceReferences($audience);

            $audience->delete();

            return [$automations, $files];
        });

        foreach ($files as [$disk, $path]) {
            if (filled($path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        return array_values($automations->map(fn (Automation $automation): string => $automation->name)->all());
    }

    /**
     * @return list<array{0: string, 1: string|null}>
     */
    private function filesOnlyThisAudienceReferences(Audience $audience): array
    {
        $files = ContactImport::query()
            ->where('audience_id', $audience->id)
            ->get(['id', 'disk', 'path'])
            ->map(fn (ContactImport $contactImport): array => [$contactImport->disk, $contactImport->path])
            ->values()
            ->all();

        SubscribeForm::query()
            ->where('audience_id', $audience->id)
            ->get(['id', 'image_path', 'logo_path', 'image_upload_path', 'image_upload_disk'])
            ->each(function (SubscribeForm $subscribeForm) use (&$files): void {
                $files[] = ['public', $subscribeForm->image_path];
                $files[] = ['public', $subscribeForm->logo_path];
                $files[] = [
                    $subscribeForm->image_upload_disk ?? StorageBackend::current()->privateDisk(),
                    $subscribeForm->image_upload_path,
                ];
            });

        return $files;
    }
}
