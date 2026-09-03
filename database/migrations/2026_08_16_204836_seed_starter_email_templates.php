<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (EmailTemplate::starters() as $starter) {
            DB::table('email_templates')->updateOrInsert(
                ['uuid' => $starter['uuid']],
                [
                    'team_id' => null,
                    'name' => $starter['name'],
                    'description' => $starter['description'],
                    'subject' => $starter['subject'],
                    'preheader' => $starter['preheader'],
                    'editor' => $starter['editor']->value,
                    'html' => $starter['html'],
                    'design' => $starter['design'] === null ? null : json_encode($starter['design']),
                    'position' => $starter['position'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('uuid', array_column(EmailTemplate::starters(), 'uuid'))
            ->delete();
    }
};
