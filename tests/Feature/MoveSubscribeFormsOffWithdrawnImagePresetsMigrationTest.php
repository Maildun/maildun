<?php

use App\Enums\SubscribeFormArtworkPreset;
use App\Enums\SubscribeFormArtworkType;
use App\Models\SubscribeForm;
use Illuminate\Support\Facades\DB;

test('moves image preset forms to the glow background and clears stale image presets on uploads', function () {
    $imagePreset = SubscribeForm::factory()->create();
    $upload = SubscribeForm::factory()->create(['image_path' => 'subscribe-form-images/hero.webp']);
    $staleBackground = SubscribeForm::factory()->create();
    $background = SubscribeForm::factory()->create([
        'artwork_type' => SubscribeFormArtworkType::BackgroundPreset,
        'artwork_preset' => SubscribeFormArtworkPreset::BackgroundOrbit,
    ]);
    DB::table('subscribe_forms')->where('id', $imagePreset->id)->update(['artwork_type' => 'image-preset', 'artwork_preset' => 'image-shore']);
    DB::table('subscribe_forms')->where('id', $upload->id)->update(['artwork_preset' => 'image-dunes']);
    DB::table('subscribe_forms')->where('id', $staleBackground->id)->update(['artwork_type' => 'background-preset', 'artwork_preset' => 'image-veil']);

    (require base_path('database/migrations/2026_10_27_142340_move_subscribe_forms_off_withdrawn_image_presets.php'))->up();

    expect($imagePreset->fresh())
        ->artwork_type->toBe(SubscribeFormArtworkType::BackgroundPreset)
        ->artwork_preset->toBe(SubscribeFormArtworkPreset::BackgroundGlow);
    expect($staleBackground->fresh())
        ->artwork_type->toBe(SubscribeFormArtworkType::BackgroundPreset)
        ->artwork_preset->toBe(SubscribeFormArtworkPreset::BackgroundGlow);
    expect($upload->fresh())
        ->artwork_type->toBe(SubscribeFormArtworkType::Upload)
        ->artwork_preset->toBeNull()
        ->image_path->toBe('subscribe-form-images/hero.webp');
    expect($background->fresh())
        ->artwork_type->toBe(SubscribeFormArtworkType::BackgroundPreset)
        ->artwork_preset->toBe(SubscribeFormArtworkPreset::BackgroundOrbit);
});
