<?php

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Schema;

test('fresh migrations preserve the consolidated application schema', function () {
    $teamIntegrationIndex = collect(Schema::getIndexes('team_email_integrations'))
        ->firstWhere('name', 'team_email_integrations_team_id_unique');
    $automationRunIndex = collect(Schema::getIndexes('automation_runs'))
        ->firstWhere('name', 'automation_runs_open_subscriber_unique');
    $senderIntegrationForeignKey = collect(Schema::getForeignKeys('team_senders'))
        ->first(fn (array $foreignKey): bool => $foreignKey['columns'] === ['verified_email_integration_id']);

    expect(Schema::hasColumns('users', [
        'uuid',
        'avatar_path',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('teams', [
            'uuid',
            'logo_path',
            'email_editor',
            'brand_color',
            'brand_font',
            'brand_input_style',
            'convert_uploads_to_webp',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('audiences', [
            'first_name_mode',
            'last_name_mode',
            'double_opt_in',
            'double_opt_in_email_id',
            'from_name',
            'from_address',
            'reply_to',
            'notification_email',
            'subscribed_url',
            'already_subscribed_url',
            'unsubscribed_url',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('subscribers', ['subscribe_form_id', 'attribute_values']))->toBeTrue()
        ->and(Schema::hasColumns('subscribe_forms', [
            'style',
            'image_side',
            'artwork_type',
            'artwork_preset',
            'image_url',
            'image_path',
            'image_upload_path',
            'logo_path',
            'logo_shape',
            'logo_size',
            'logo_position',
            'header_spacing',
            'card_padding',
            'redirect_enabled',
            'redirect_url',
            'powered_by_enabled',
            'powered_by_form_position',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('subscribe_forms', 'first_name_mode'))->toBeFalse()
        ->and(Schema::hasColumns('email_templates', ['subject', 'preheader']))->toBeTrue()
        ->and(Schema::hasColumns('emails', ['sent_at', 'status', 'batch_id', 'recipient_count', 'send_started_at']))->toBeTrue()
        ->and(Schema::hasColumns('email_deliveries', ['send_attempted_at', 'uses_team_email_integration']))->toBeTrue()
        ->and(Schema::hasColumn('transactional_email_deliveries', 'uses_team_email_integration'))->toBeTrue()
        ->and(Schema::hasColumns('team_email_integrations', [
            'name',
            'verification_version',
            'test_from_address',
            'ses_sns_topic_arn_hash',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('team_senders', [
            'verified_email_integration_id',
            'verified_email_integration_version',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('teams', 'requires_email_integration'))->toBeFalse()
        ->and(Schema::hasColumn('teams', 'active_email_integration_id'))->toBeFalse()
        ->and($teamIntegrationIndex['unique'] ?? null)->toBeTrue()
        ->and($automationRunIndex['unique'] ?? null)->toBeTrue()
        ->and($senderIntegrationForeignKey['foreign_table'] ?? null)->toBe('team_email_integrations')
        ->and(EmailTemplate::query()->whereNull('team_id')->count())->toBe(4);
});
