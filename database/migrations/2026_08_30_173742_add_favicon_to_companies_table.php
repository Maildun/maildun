<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('favicon')->nullable()->after('normalized_name');
        });

        DB::table('company_domains')
            ->orderBy('id')
            ->chunkById(100, function (Collection $companyDomains): void {
                foreach ($companyDomains as $companyDomain) {
                    DB::table('companies')
                        ->where('id', $companyDomain->company_id)
                        ->whereNull('favicon')
                        ->update([
                            'favicon' => 'https://www.google.com/s2/favicons?domain='.rawurlencode($companyDomain->domain).'&sz=64',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('favicon');
        });
    }
};
