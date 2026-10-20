<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Earlier image presets were renamed when the photographs shipped.
     * Installations that never stored the old values are unchanged.
     *
     * @var array<string, string>
     */
    private array $map = [
        'image-aurora' => 'image-pastel',
        'image-horizon' => 'image-ember',
        'image-prism' => 'image-golden',
        'image-nocturne' => 'image-cumulus',
        'image-drift' => 'image-dunes',
        'image-flare' => 'image-shore',
    ];

    public function up(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('subscribe_forms')->where('artwork_preset', $old)->update(['artwork_preset' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('subscribe_forms')->where('artwork_preset', $new)->update(['artwork_preset' => $old]);
        }
    }
};
