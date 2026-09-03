<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable();
            $table->string('subdivision_code', 16)->nullable();
            $table->string('subdivision_name', 128)->nullable();
            $table->string('city_name', 128)->nullable();
            $table->decimal('latitude', total: 9, places: 6)->nullable();
            $table->decimal('longitude', total: 9, places: 6)->nullable();
            $table->unsignedBigInteger('network_asn')->nullable();
            $table->string('network_name')->nullable();
            $table->string('geolocation_source', 32)->nullable();
            $table->timestamp('geolocated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_tracking_events', function (Blueprint $table) {
            $table->dropColumn([
                'country_code',
                'subdivision_code',
                'subdivision_name',
                'city_name',
                'latitude',
                'longitude',
                'network_asn',
                'network_name',
                'geolocation_source',
                'geolocated_at',
            ]);
        });
    }
};
