<?php

use App\Enums\SubscribeFormArtworkPreset;
use App\Enums\SubscribeFormArtworkType;
use App\Enums\SubscribeFormCardPadding;
use App\Enums\SubscribeFormHeaderSpacing;
use App\Enums\SubscribeFormLogoPosition;
use App\Enums\SubscribeFormLogoShape;
use App\Enums\SubscribeFormLogoSize;
use App\Enums\SubscribeFormPoweredByPosition;
use App\Enums\TeamBrandColor;
use App\Enums\TeamBrandFont;
use App\Enums\TeamBrandInputStyle;
use App\Enums\TeamRole;
use App\Jobs\ProcessSubscribeFormImage;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\SubscribeForm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function subscribeFormPayload(array $overrides = []): array
{
    return [
        'name' => 'Footer signup',
        'headline' => 'Get product news',
        'description' => 'A short monthly update.',
        'text_alignment' => 'center',
        'button_label' => 'Join now',
        'success_heading' => 'You’re subscribed!',
        'success_message' => 'You are subscribed.',
        'consent_text' => 'I agree to receive marketing emails.',
        ...$overrides,
    ];
}

test('subscribe forms created without a success heading use the default heading', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $payload = subscribeFormPayload();
    unset($payload['success_heading']);

    $this->actingAs($user)
        ->post(route('audiences.subscribe_forms.store', [$team, $audience]), $payload)
        ->assertSessionHasNoErrors();

    expect($audience->subscribeForms()->firstOrFail()->success_heading)->toBe('You’re subscribed!');

    $subscribeForm = $audience->subscribeForms()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload(['success_heading' => '']))
        ->assertSessionHasErrors('success_heading');
});

test('enabled subscribe form redirects require an http or https destination', function (?string $redirectUrl) {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('audiences.subscribe_forms.store', [$team, $audience]), subscribeFormPayload([
            'redirect_enabled' => true,
            'redirect_url' => $redirectUrl,
        ]))
        ->assertSessionHasErrors('redirect_url');
})->with([
    'missing destination' => null,
    'unsupported protocol' => 'javascript:alert(1)',
]);

test('owners can create edit publish unpublish and soft delete subscribe forms', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update([
        'brand_color' => TeamBrandColor::Fuchsia,
        'brand_font' => TeamBrandFont::InstrumentSans,
        'brand_input_style' => TeamBrandInputStyle::Soft,
    ]);
    $audience = Audience::factory()->for($team)->create();
    $attribute = AudienceAttribute::factory()->for($audience)->create([
        'name' => 'Company',
        'key' => 'company',
    ]);

    $response = $this->actingAs($user)
        ->post(route('audiences.subscribe_forms.store', [$team, $audience]), subscribeFormPayload());

    $subscribeForm = $audience->subscribeForms()->firstOrFail();

    $response->assertRedirect(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]));

    $this->actingAs($user)
        ->get(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('subscribe-forms/edit')
            ->where('canManage', true)
            ->where('subscribeForm.uuid', $subscribeForm->uuid)
            ->where('subscribeForm.published', false)
            ->where('subscribeForm.public_url', route('public.subscribe_forms.show', $subscribeForm))
            ->where('subscribeForm.embed_code', fn (string $code) => str_contains($code, '?embed=1'))
            ->where('subscribeForm.style', 'card')
            ->where('subscribeForm.image_side', 'right')
            ->where('subscribeForm.artwork_type', 'upload')
            ->where('subscribeForm.artwork_preset', null)
            ->where('subscribeForm.text_alignment', 'center')
            ->where('subscribeForm.image_url', null)
            ->where('subscribeForm.logo', null)
            ->where('subscribeForm.logo_shape', 'default')
            ->where('subscribeForm.logo_size', 'medium')
            ->where('subscribeForm.logo_position', 'center')
            ->where('subscribeForm.header_spacing', 'default')
            ->where('subscribeForm.card_padding', 'default')
            ->where('subscribeForm.theme.color', 'fuchsia')
            ->where('subscribeForm.theme.font', 'instrument-sans')
            ->where('subscribeForm.theme.inputStyle', 'soft')
            ->where('subscribeForm.success_heading', 'You’re subscribed!')
            ->where('subscribeForm.redirect_enabled', false)
            ->where('subscribeForm.redirect_url', null)
            ->where('subscribeForm.powered_by_enabled', true)
            ->where('subscribeForm.powered_by_form_position', 'bottom-center')
            ->where('attributes.0.uuid', $attribute->uuid)
            ->where('attributes.0.key', 'company')
            ->has('styles', 4)
            ->has('artworkPresets', 10)
            ->has('brandColors', 15)
            ->has('brandFonts', 8)
            ->has('brandInputStyles', 3));

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'headline' => 'Updated headline',
            'text_alignment' => 'left',
            'success_heading' => 'Welcome aboard!',
            'redirect_enabled' => true,
            'redirect_url' => 'https://example.com/thank-you',
            'powered_by_enabled' => true,
            'powered_by_form_position' => 'top-left',
            'brand_color' => 'orange',
            'brand_font' => 'mono',
            'brand_input_style' => 'underline',
        ]))
        ->assertRedirect();

    expect($subscribeForm->fresh())
        ->headline->toBe('Updated headline')
        ->text_alignment->value->toBe('left')
        ->success_heading->toBe('Welcome aboard!')
        ->redirect_enabled->toBeTrue()
        ->redirect_url->toBe('https://example.com/thank-you')
        ->powered_by_enabled->toBeTrue()
        ->powered_by_form_position->toBe(SubscribeFormPoweredByPosition::TopLeft)
        ->brand_color->toBe(TeamBrandColor::Orange)
        ->brand_font->toBe(TeamBrandFont::Mono)
        ->brand_input_style->toBe(TeamBrandInputStyle::Underline);

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'style' => 'split',
            'image_side' => 'left',
        ]))
        ->assertRedirect();

    expect($subscribeForm->fresh())
        ->style->value->toBe('split')
        ->image_side->value->toBe('left');

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'style' => 'not-a-layout',
            'text_alignment' => 'diagonal',
            'brand_color' => 'magenta',
            'brand_font' => 'comic-sans',
            'brand_input_style' => 'pill',
            'redirect_enabled' => true,
            'powered_by_form_position' => 'middle-left',
            'publish' => 'eventually',
        ]))
        ->assertInvalid([
            'style',
            'text_alignment',
            'brand_color',
            'brand_font',
            'brand_input_style',
            'redirect_url',
            'powered_by_form_position',
            'publish',
        ]);

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.publish', [$team, $audience, $subscribeForm]))
        ->assertRedirect();
    expect($subscribeForm->fresh()->published_at)->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.unpublish', [$team, $audience, $subscribeForm]))
        ->assertRedirect();
    expect($subscribeForm->fresh()->published_at)->toBeNull();

    $this->actingAs($user)
        ->delete(route('audiences.subscribe_forms.destroy', [$team, $audience, $subscribeForm]))
        ->assertRedirect(route('audiences.show', [$team, $audience]));

    $this->assertSoftDeleted($subscribeForm);
});

test('owners can save changes and publish a subscribe form atomically', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create([
        'headline' => 'Old headline',
    ]);

    $this->actingAs($user)
        ->patch(
            route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]),
            subscribeFormPayload([
                'headline' => 'Published headline',
                'publish' => true,
            ]),
        )
        ->assertRedirect();

    expect($subscribeForm->fresh())
        ->headline->toBe('Published headline')
        ->published_at->not->toBeNull();
});

test('owners can upload replace and remove subscribe form logos', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'logo' => UploadedFile::fake()->image('logo.png')->size(2048),
            'logo_shape' => SubscribeFormLogoShape::Square->value,
            'logo_size' => SubscribeFormLogoSize::Large->value,
        ]))
        ->assertRedirect();

    $subscribeForm->refresh();

    expect($subscribeForm)
        ->logo_path->not->toBeNull()
        ->logo_shape->value->toBe(SubscribeFormLogoShape::Square->value)
        ->logo_size->value->toBe(SubscribeFormLogoSize::Large->value)
        ->logo->toStartWith('/storage/');

    Storage::disk('public')->assertExists($subscribeForm->logo_path);

    $this->actingAs($user)
        ->get(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.logo', $subscribeForm->logo)
            ->where('subscribeForm.logo_shape', 'square')
            ->where('subscribeForm.logo_size', 'large'));

    $originalLogoPath = $subscribeForm->logo_path;

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'logo' => UploadedFile::fake()->image('logo-two.png'),
            'logo_shape' => SubscribeFormLogoShape::Default->value,
            'logo_size' => SubscribeFormLogoSize::Small->value,
        ]))
        ->assertRedirect();

    $subscribeForm->refresh();

    expect($subscribeForm->logo_path)->not->toBe($originalLogoPath)
        ->and($subscribeForm->logo_shape->value)->toBe('default')
        ->and($subscribeForm->logo_size->value)->toBe('small');
    Storage::disk('public')->assertMissing($originalLogoPath);
    Storage::disk('public')->assertExists($subscribeForm->logo_path);

    $replacedLogoPath = $subscribeForm->logo_path;

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'remove_logo' => true,
        ]))
        ->assertRedirect();

    $subscribeForm->refresh();

    expect($subscribeForm->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($replacedLogoPath);
});

test('owners can save logo shape position header spacing and card padding', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'logo_shape' => SubscribeFormLogoShape::RoundedFull->value,
            'logo_size' => SubscribeFormLogoSize::Large->value,
            'logo_position' => SubscribeFormLogoPosition::Left->value,
            'header_spacing' => SubscribeFormHeaderSpacing::Spacious->value,
            'card_padding' => SubscribeFormCardPadding::Compact->value,
        ]))
        ->assertRedirect();

    expect($subscribeForm->fresh())
        ->logo_shape->toBe(SubscribeFormLogoShape::RoundedFull)
        ->logo_size->toBe(SubscribeFormLogoSize::Large)
        ->logo_position->toBe(SubscribeFormLogoPosition::Left)
        ->header_spacing->toBe(SubscribeFormHeaderSpacing::Spacious)
        ->card_padding->toBe(SubscribeFormCardPadding::Compact);

    $this->actingAs($user)
        ->get(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.logo_shape', 'rounded-full')
            ->where('subscribeForm.logo_position', 'left')
            ->where('subscribeForm.header_spacing', 'spacious')
            ->where('subscribeForm.card_padding', 'compact'));
});

test('owners can upload subscribe form artwork that is converted to webp in the background', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create([
        'image_url' => 'https://example.com/legacy-image.jpg',
        'artwork_type' => SubscribeFormArtworkType::BackgroundPreset,
        'artwork_preset' => SubscribeFormArtworkPreset::BackgroundMatrix,
    ]);

    $this->actingAs($user)
        ->post(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), [
            ...subscribeFormPayload(),
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('hero.png', 1600, 900)->size(2048),
        ])
        ->assertRedirect();

    $subscribeForm->refresh();
    $sourcePath = $subscribeForm->image_upload_path;

    expect($sourcePath)->toStartWith('subscribe-form-images/pending/')
        ->and($subscribeForm->image_path)->toBeNull()
        ->and($subscribeForm->image)->toBe('https://example.com/legacy-image.jpg')
        ->and($subscribeForm->artwork_type)->toBe(SubscribeFormArtworkType::Upload);
    Storage::disk('local')->assertExists($sourcePath);

    Queue::assertPushed(ProcessSubscribeFormImage::class, function (ProcessSubscribeFormImage $job) use ($subscribeForm, $sourcePath): bool {
        return $job->subscribeFormId === $subscribeForm->id
            && $job->sourcePath === $sourcePath;
    });

    (new ProcessSubscribeFormImage($subscribeForm->id, $sourcePath))->handle();

    $subscribeForm->refresh();

    expect($subscribeForm->image_upload_path)->toBeNull()
        ->and($subscribeForm->image_path)->toStartWith('subscribe-form-images/')
        ->and($subscribeForm->image_path)->toEndWith('.webp')
        ->and($subscribeForm->image)->toBe('/storage/'.$subscribeForm->image_path);
    Storage::disk('local')->assertMissing($sourcePath);
    Storage::disk('public')->assertExists($subscribeForm->image_path);
    expect(Storage::disk('public')->mimeType($subscribeForm->image_path))->toBe('image/webp');
});

test('owners can choose artwork presets without deleting an uploaded image', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create([
        'image_path' => 'subscribe-form-images/hero.webp',
    ]);
    Storage::disk('public')->put($subscribeForm->image_path, 'image');

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'style' => 'cover',
            'artwork_type' => SubscribeFormArtworkType::ImagePreset->value,
            'artwork_preset' => SubscribeFormArtworkPreset::ImageFlare->value,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($subscribeForm->fresh())
        ->artwork_type->toBe(SubscribeFormArtworkType::ImagePreset)
        ->artwork_preset->toBe(SubscribeFormArtworkPreset::ImageFlare)
        ->image_path->toBe('subscribe-form-images/hero.webp');
    Storage::disk('public')->assertExists('subscribe-form-images/hero.webp');

    $this->actingAs($user)
        ->get(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscribeForm.artwork_type', 'image-preset')
            ->where('subscribeForm.artwork_preset', 'image-flare')
            ->has('artworkPresets', 10)
            ->where('artworkPresets.0', [
                'value' => 'image-aurora',
                'label' => 'Halo',
                'artwork_type' => 'image-preset',
            ])
            ->where('artworkPresets.4', [
                'value' => 'image-drift',
                'label' => 'Drift',
                'artwork_type' => 'image-preset',
            ])
            ->where('artworkPresets.5', [
                'value' => 'image-flare',
                'label' => 'Flare',
                'artwork_type' => 'image-preset',
            ]));
});

test('artwork presets must match the selected artwork type', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'artwork_type' => SubscribeFormArtworkType::ImagePreset->value,
            'artwork_preset' => SubscribeFormArtworkPreset::BackgroundGrid->value,
        ]))
        ->assertSessionHasErrors([
            'artwork_preset' => 'The selected artwork preset does not match its type.',
        ]);

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'artwork_type' => SubscribeFormArtworkType::BackgroundPreset->value,
            'artwork_preset' => null,
        ]))
        ->assertSessionHasErrors([
            'artwork_preset' => 'Choose an artwork preset.',
        ]);

    expect($subscribeForm->fresh())
        ->artwork_type->toBe(SubscribeFormArtworkType::Upload)
        ->artwork_preset->toBeNull();
});

test('subscribe form artwork conversion carries its s3 compatible source disk to the queue', function () {
    config()->set('filesystems.default', 's3');
    Queue::fake();
    Storage::fake('s3');
    Storage::fake('public', ['url' => 'https://assets.example.com']);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->post(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), [
            ...subscribeFormPayload(),
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('hero.png', 1600, 900),
        ])
        ->assertRedirect();

    $subscribeForm->refresh();
    $sourcePath = $subscribeForm->image_upload_path;

    Storage::disk('s3')->assertExists($sourcePath);
    Queue::assertPushed(ProcessSubscribeFormImage::class, function (ProcessSubscribeFormImage $job) use ($subscribeForm, $sourcePath): bool {
        return $job->subscribeFormId === $subscribeForm->id
            && $job->sourcePath === $sourcePath
            && $job->sourceDisk === 's3';
    });

    (new ProcessSubscribeFormImage($subscribeForm->id, $sourcePath, 's3'))->handle();

    $subscribeForm->refresh();

    expect($subscribeForm->image_upload_path)->toBeNull()
        ->and($subscribeForm->image_path)->toEndWith('.webp')
        ->and($subscribeForm->image)->toBe('https://assets.example.com/'.$subscribeForm->image_path);
    Storage::disk('s3')->assertMissing($sourcePath);
    Storage::disk('public')->assertExists($subscribeForm->image_path);
});

test('a stale subscribe form artwork conversion cannot replace a newer upload', function () {
    Storage::fake('local');
    Storage::fake('public');

    $subscribeForm = SubscribeForm::factory()->create([
        'image_upload_path' => 'subscribe-form-images/pending/newer-image.png',
    ]);
    $staleSourcePath = 'subscribe-form-images/pending/stale-image.png';
    Storage::disk('local')->put($staleSourcePath, UploadedFile::fake()->image('stale.png')->getContent());

    (new ProcessSubscribeFormImage($subscribeForm->id, $staleSourcePath))->handle();

    expect($subscribeForm->fresh()->image_upload_path)->toBe('subscribe-form-images/pending/newer-image.png');
    Storage::disk('local')->assertMissing($staleSourcePath);
});

test('owners can remove subscribe form artwork and its pending upload', function () {
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create([
        'image_path' => 'subscribe-form-images/hero.webp',
        'image_upload_path' => 'subscribe-form-images/pending/hero.png',
        'image_url' => 'https://example.com/legacy-image.jpg',
    ]);
    Storage::disk('public')->put($subscribeForm->image_path, 'image');
    Storage::disk('local')->put($subscribeForm->image_upload_path, 'image');

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'remove_image' => true,
        ]))
        ->assertRedirect();

    expect($subscribeForm->fresh())
        ->image_path->toBeNull()
        ->image_upload_path->toBeNull()
        ->image_url->toBeNull()
        ->image->toBeNull();
    Storage::disk('public')->assertMissing('subscribe-form-images/hero.webp');
    Storage::disk('local')->assertMissing('subscribe-form-images/pending/hero.png');
});

test('subscribe form logos can be uploaded through a spoofed patch post', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->post(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), [
            ...subscribeFormPayload(),
            '_method' => 'PATCH',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'logo_shape' => SubscribeFormLogoShape::Square->value,
            'logo_size' => SubscribeFormLogoSize::Large->value,
        ])
        ->assertRedirect();

    $subscribeForm->refresh();

    expect($subscribeForm)
        ->logo_path->not->toBeNull()
        ->logo_shape->value->toBe('square')
        ->logo_size->value->toBe('large');
    Storage::disk('public')->assertExists($subscribeForm->logo_path);
});

test('subscribe form logo uploads reject invalid files and values', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'image' => UploadedFile::fake()->create('artwork.pdf', 100, 'application/pdf'),
            'logo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]))
        ->assertInvalid(['image', 'logo']);

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'logo_shape' => 'wide',
            'logo_size' => 'huge',
            'logo_position' => 'middle',
            'header_spacing' => 'huge',
            'card_padding' => 'huge',
        ]))
        ->assertInvalid(['logo_shape', 'logo_size', 'logo_position', 'header_spacing', 'card_padding']);
});

test('subscribe form artwork and logos reject files larger than two megabytes', function () {
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $response = $this->actingAs($user)
        ->post(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), [
            ...subscribeFormPayload(),
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('artwork.png')->size(2049),
            'logo' => UploadedFile::fake()->image('logo.png')->size(2049),
        ]);

    $response->assertSessionHasErrors([
        'image' => 'Artwork must be 2 MB or smaller.',
        'logo' => 'Logo must be 2 MB or smaller.',
    ]);
    expect($subscribeForm->fresh())
        ->image_upload_path->toBeNull()
        ->logo_path->toBeNull();
    Queue::assertNothingPushed();
});

test('updating a subscribe form without a new logo keeps the existing file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]))
        ->assertRedirect();

    $logoPath = $subscribeForm->refresh()->logo_path;

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.update', [$team, $audience, $subscribeForm]), subscribeFormPayload([
            'headline' => 'Still the same logo',
        ]))
        ->assertRedirect();

    expect($subscribeForm->fresh())
        ->headline->toBe('Still the same logo')
        ->logo_path->toBe($logoPath);
    Storage::disk('public')->assertExists($logoPath);
});

test('members can view subscribe forms but cannot change them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    assignViewerRole($team, $member);
    $audience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($member)
        ->get(route('audiences.subscribe_forms.edit', [$team, $audience, $subscribeForm]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($member)
        ->patch(route('audiences.subscribe_forms.publish', [$team, $audience, $subscribeForm]))
        ->assertForbidden();
});

test('subscribe form nested bindings prevent cross audience changes', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    $otherAudience = Audience::factory()->for($team)->create();
    $subscribeForm = SubscribeForm::factory()->for($audience)->create();

    $this->actingAs($user)
        ->patch(route('audiences.subscribe_forms.publish', [$team, $otherAudience, $subscribeForm]))
        ->assertNotFound();
});
