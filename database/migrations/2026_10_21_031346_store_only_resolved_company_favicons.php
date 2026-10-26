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
        // A Google favicon URL for a 253-character domain is longer than varchar(255).
        Schema::table('companies', function (Blueprint $table): void {
            $table->text('favicon')->nullable()->change();
        });

        // Placeholder icons are now signed when they are read, so a stored signed URL
        // would stop loading once the app key changes. Null means "use the placeholder".
        DB::table('companies')
            ->whereNotNull('favicon')
            ->where('favicon', 'not like', 'https://www.google.com/s2/favicons?%')
            ->update(['favicon' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Longer Google URLs would not fit back into varchar(255); drop them so the
        // placeholder shows instead of failing the rollback.
        DB::table('companies')
            ->whereRaw('LENGTH(favicon) > 255')
            ->update(['favicon' => null]);

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('favicon')->nullable()->change();
        });
    }
};
