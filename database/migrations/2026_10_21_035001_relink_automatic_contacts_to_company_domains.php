<?php

use App\Models\CompanyDomain;
use App\Services\ResolveContactCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The contact backfill created contacts without company matching. Servers
     * that already had company domains when it ran left those contacts
     * unlinked, so relink every automatic contact to its domain's company.
     * Manual assignments are never touched.
     */
    public function up(): void
    {
        $resolveContactCompany = app(ResolveContactCompany::class);

        CompanyDomain::query()
            ->orderBy('id')
            ->get(['team_id', 'domain'])
            ->groupBy('team_id')
            ->each(fn (Collection $domains, int $teamId) => $resolveContactCompany->relinkDomains(
                $teamId,
                array_values($domains->map(fn (CompanyDomain $domain): string => $domain->domain)->all()),
            ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Relinked companies follow the contacts' email domains and stay linked.
    }
};
