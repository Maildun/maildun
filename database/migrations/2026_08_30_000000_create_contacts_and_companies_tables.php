<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->timestamps();

            $table->unique(['team_id', 'normalized_name']);
            $table->index(['team_id', 'created_at']);
        });

        Schema::create('company_domains', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->timestamps();

            $table->unique(['team_id', 'domain']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_assignment_mode')->default('automatic');
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'email']);
            $table->index(['team_id', 'company_id']);
            $table->index(['team_id', 'created_at']);
        });

        Schema::create('contact_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'tag_id']);
        });

        Schema::table('subscribers', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->after('audience_id')->constrained()->cascadeOnDelete();
            $table->index(['contact_id', 'created_at']);
        });

        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->after('subscriber_id')->constrained()->nullOnDelete();
            $table->index(['contact_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['contact_id', 'sent_at']);
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::table('subscribers', function (Blueprint $table): void {
            $table->dropIndex(['contact_id', 'created_at']);
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('company_domains');
        Schema::dropIfExists('companies');
    }
};
