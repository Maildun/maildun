<?php

namespace App\Http\Controllers;

use App\Enums\StorageBackend;
use App\Enums\SubscribeFormArtworkPreset;
use App\Enums\SubscribeFormArtworkType;
use App\Enums\SubscribeFormStyle;
use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use App\Http\Requests\SaveSubscribeFormRequest;
use App\Jobs\ProcessSubscribeFormImage;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\SubscribeForm;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SubscribeFormController extends Controller
{
    public function store(SaveSubscribeFormRequest $request, Team $currentTeam, Audience $audience): RedirectResponse
    {
        Gate::authorize('create', [SubscribeForm::class, $audience]);
        $subscribeForm = $audience->subscribeForms()->create([
            'brand_color' => $currentTeam->brand_color,
            'brand_font' => $currentTeam->brand_font,
            'brand_input_style' => $currentTeam->brand_input_style,
            ...$request->formAttributes(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscribe form created.')]);

        return to_route('audiences.subscribe_forms.edit', [
            'current_team' => $audience->team,
            'audience' => $audience,
            'subscribeForm' => $subscribeForm,
        ]);
    }

    public function edit(Team $currentTeam, Audience $audience, SubscribeForm $subscribeForm): Response
    {
        Gate::authorize('view', $subscribeForm);

        $publicUrl = route('public.subscribe_forms.show', $subscribeForm);

        return Inertia::render('subscribe-forms/edit', [
            'audience' => [
                'uuid' => $audience->uuid,
                'name' => $audience->name,
                'first_name_mode' => $audience->first_name_mode->value,
                'last_name_mode' => $audience->last_name_mode->value,
            ],
            'subscribeForm' => [
                'uuid' => $subscribeForm->uuid,
                'name' => $subscribeForm->name,
                'headline' => $subscribeForm->headline,
                'description' => $subscribeForm->description,
                'text_alignment' => $subscribeForm->text_alignment->value,
                'button_label' => $subscribeForm->button_label,
                'success_heading' => $subscribeForm->success_heading,
                'success_message' => $subscribeForm->success_message,
                'redirect_enabled' => $subscribeForm->redirect_enabled,
                'redirect_url' => $subscribeForm->redirect_url,
                'powered_by_enabled' => true,
                'powered_by_form_position' => $subscribeForm->powered_by_form_position->value,
                'consent_text' => $subscribeForm->consent_text,
                'style' => $subscribeForm->style->value,
                'image_side' => $subscribeForm->image_side->value,
                'artwork_type' => $subscribeForm->artwork_type->value,
                'artwork_preset' => $subscribeForm->artwork_preset?->value,
                'image_url' => $subscribeForm->image,
                'image_processing' => filled($subscribeForm->image_upload_path),
                'logo' => $subscribeForm->logo,
                'logo_shape' => $subscribeForm->logo_shape->value,
                'logo_size' => $subscribeForm->logo_size->value,
                'logo_position' => $subscribeForm->logo_position->value,
                'header_spacing' => $subscribeForm->header_spacing->value,
                'card_padding' => $subscribeForm->card_padding->value,
                'theme' => [
                    'color' => $subscribeForm->brand_color->value,
                    'font' => $subscribeForm->brand_font->value,
                    'inputStyle' => $subscribeForm->brand_input_style->value,
                ],
                'published' => $subscribeForm->isPublished(),
                'public_url' => $publicUrl,
                'embed_code' => sprintf('<iframe src="%s?embed=1" width="100%%" height="560" frameborder="0" title="%s"></iframe>', $publicUrl, e($subscribeForm->headline)),
            ],
            'styles' => SubscribeFormStyle::options(),
            'artworkPresets' => SubscribeFormArtworkPreset::options(),
            'brandColors' => TeamBrandColor::options(),
            'brandFonts' => TeamBrandFont::options(),
            'brandInputStyles' => TeamBrandInputStyle::options(),
            'attributes' => $audience->audienceAttributes()
                ->orderBy('position')
                ->get()
                ->map(fn (AudienceAttribute $attribute): array => [
                    'uuid' => $attribute->uuid,
                    'name' => $attribute->name,
                    'key' => $attribute->key,
                    'type' => $attribute->type->value,
                    'required' => $attribute->required,
                ])
                ->values(),
            'canManage' => Gate::allows('update', $subscribeForm),
        ]);
    }

    public function update(SaveSubscribeFormRequest $request, Team $currentTeam, Audience $audience, SubscribeForm $subscribeForm): RedirectResponse
    {
        Gate::authorize('update', $subscribeForm);

        $oldImagePath = null;
        $oldImageUploadPath = null;
        $oldImageUploadDisk = null;
        $oldLogoPath = null;
        $storedImageUploadPath = null;
        $sourceDisk = StorageBackend::current()->privateDisk();

        DB::transaction(function () use ($request, $subscribeForm, $sourceDisk, &$oldImagePath, &$oldImageUploadPath, &$oldImageUploadDisk, &$oldLogoPath, &$storedImageUploadPath): void {
            $subscribeForm = SubscribeForm::whereKey($subscribeForm->id)->lockForUpdate()->firstOrFail();
            $subscribeForm->fill($request->formAttributes());

            if ($request->boolean('publish')) {
                $subscribeForm->fill(['published_at' => now()]);
            }

            if ($request->hasFile('image')) {
                $storedImageUploadPath = $request->file('image')->store('subscribe-form-images/pending', $sourceDisk);

                if ($storedImageUploadPath === false) {
                    throw new RuntimeException('Unable to store the uploaded subscribe form image.');
                }

                $oldImageUploadPath = $subscribeForm->image_upload_path;
                $oldImageUploadDisk = $subscribeForm->image_upload_disk;
                $subscribeForm->image_upload_path = $storedImageUploadPath;
                $subscribeForm->image_upload_disk = $sourceDisk;
                $subscribeForm->artwork_type = SubscribeFormArtworkType::Upload;
            } elseif ($request->boolean('remove_image')) {
                $oldImagePath = $subscribeForm->image_path;
                $oldImageUploadPath = $subscribeForm->image_upload_path;
                $oldImageUploadDisk = $subscribeForm->image_upload_disk;
                $subscribeForm->forceFill([
                    'image_path' => null,
                    'image_upload_path' => null,
                    'image_upload_disk' => null,
                    'image_url' => null,
                ]);
            }

            if ($request->hasFile('logo')) {
                $storedPath = $request->file('logo')->store('subscribe-form-logos', 'public');

                if ($storedPath === false) {
                    throw new RuntimeException('Unable to store the uploaded subscribe form logo.');
                }

                $oldLogoPath = $subscribeForm->getRawOriginal('logo_path');
                $subscribeForm->logo_path = $storedPath;
            } elseif ($request->boolean('remove_logo')) {
                $oldLogoPath = $subscribeForm->getRawOriginal('logo_path');
                $subscribeForm->logo_path = null;
            }

            $subscribeForm->save();
        });

        if ($oldLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        if ($oldImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        if ($oldImageUploadPath) {
            /* The superseded upload may predate a backend switch, so delete it
             * from the disk it was actually written to. */
            Storage::disk($oldImageUploadDisk ?? $sourceDisk)->delete($oldImageUploadPath);
        }

        if ($storedImageUploadPath) {
            ProcessSubscribeFormImage::dispatch($subscribeForm->id, $storedImageUploadPath, $sourceDisk);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->boolean('publish')
                ? __('Subscribe form saved and published.')
                : __('Subscribe form updated.'),
        ]);

        return back();
    }

    public function publish(Team $currentTeam, Audience $audience, SubscribeForm $subscribeForm): RedirectResponse
    {
        Gate::authorize('update', $subscribeForm);
        $subscribeForm->update(['published_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscribe form published.')]);

        return back();
    }

    public function unpublish(Team $currentTeam, Audience $audience, SubscribeForm $subscribeForm): RedirectResponse
    {
        Gate::authorize('update', $subscribeForm);
        $subscribeForm->update(['published_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscribe form unpublished.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Audience $audience, SubscribeForm $subscribeForm): RedirectResponse
    {
        Gate::authorize('delete', $subscribeForm);
        $subscribeForm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscribe form deleted.')]);

        return to_route('audiences.show', ['current_team' => $audience->team, 'audience' => $audience]);
    }
}
