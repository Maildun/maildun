<?php

use App\Enums\StorageBackend;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which private disk holds artwork awaiting WebP conversion, so
     * storage:sync can repoint in-flight uploads when the storage backend
     * changes instead of stranding them on the old one.
     */
    public function up(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table) {
            $table->string('image_upload_disk')->nullable()->after('image_upload_path');
        });

        /* Existing pending uploads went to whatever the default disk was. */
        DB::table('subscribe_forms')
            ->whereNotNull('image_upload_path')
            ->update(['image_upload_disk' => StorageBackend::current()->privateDisk()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribe_forms', function (Blueprint $table) {
            $table->dropColumn('image_upload_disk');
        });
    }
};
