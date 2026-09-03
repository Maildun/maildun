<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table): void {
            $table->string('brand_color')->default('blue');
            $table->string('brand_font')->default('inter');
            $table->string('brand_input_style')->default('default');
        });

        DB::table('subscribe_forms')
            ->join('audiences', 'audiences.id', '=', 'subscribe_forms.audience_id')
            ->join('teams', 'teams.id', '=', 'audiences.team_id')
            ->select([
                'subscribe_forms.id',
                'teams.brand_color',
                'teams.brand_font',
                'teams.brand_input_style',
            ])
            ->orderBy('subscribe_forms.id')
            ->get()
            ->each(function (object $form): void {
                DB::table('subscribe_forms')
                    ->where('id', $form->id)
                    ->update([
                        'brand_color' => $form->brand_color,
                        'brand_font' => $form->brand_font,
                        'brand_input_style' => $form->brand_input_style,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table): void {
            $table->dropColumn([
                'brand_color',
                'brand_font',
                'brand_input_style',
            ]);
        });
    }
};
