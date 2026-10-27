<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The bundled image presets were withdrawn because the source and license
     * of their artwork were never recorded. Forms that showed one move to
     * the Glow background, the softest remaining preset, and an image preset
     * left behind on an upload form is cleared so the enum cast can read it.
     */
    public function up(): void
    {
        DB::table('subscribe_forms')
            ->where(fn (Builder $query): Builder => $query
                ->where('artwork_type', 'image-preset')
                ->orWhere(fn (Builder $query): Builder => $query
                    ->where('artwork_type', 'background-preset')
                    ->where('artwork_preset', 'like', 'image-%')))
            ->update([
                'artwork_type' => 'background-preset',
                'artwork_preset' => 'background-glow',
            ]);

        DB::table('subscribe_forms')
            ->where('artwork_preset', 'like', 'image-%')
            ->update(['artwork_preset' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The image presets no longer exist, so there is nothing to restore.
    }
};
