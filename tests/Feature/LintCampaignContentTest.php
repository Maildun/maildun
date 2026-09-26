<?php

use App\Actions\Emails\LintCampaignContent;
use App\Models\Audience;
use App\Models\AudienceAttribute;
use App\Models\Email;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 * @return list<string>
 */
function lintCodes(array $attributes, ?Audience $audience = null): array
{
    $email = Email::factory()->create([
        'audience_id' => $audience?->id,
        'subject' => 'Our September update',
        'preheader' => 'What changed this month',
        'html' => '<p>Hello</p>',
        ...$attributes,
    ]);

    return array_column(app(LintCampaignContent::class)->handle($email), 'code');
}

test('a clean campaign has nothing to check', function () {
    expect(lintCodes([]))->toBe([]);
});

test('each content problem is reported', function (array $attributes, string $code) {
    expect(lintCodes($attributes))->toContain($code);
})->with([
    'a body Gmail would clip' => [['html' => '<p>'.str_repeat('a', LintCampaignContent::GMAIL_CLIP_BYTES).'</p>'], 'gmail_clipping'],
    'a merge tag that is not an audience field' => [['html' => '<p>Hi {{ nickname }}</p>'], 'unknown_merge_tags'],
    'a merge tag with the wrong case' => [['subject' => 'Hi {{ First_Name }}'], 'unknown_merge_tags'],
    'an image without alt text' => [['html' => '<img src="https://example.com/a.png">'], 'images_without_alt'],
    'an http link' => [['html' => '<a href="http://example.com">Read</a>'], 'insecure_links'],
    'an all-caps subject' => [['subject' => 'HUGE SALE TODAY'], 'shouting_subject'],
    'repeated punctuation in the subject' => [['subject' => 'Last chance!!!'], 'subject_punctuation'],
    'no preheader' => [['preheader' => null], 'missing_preheader'],
]);

test('standard tags and the audience fields are known merge tags', function () {
    $audience = Audience::factory()->create();
    AudienceAttribute::factory()->for($audience)->create(['key' => 'company']);

    expect(lintCodes([
        'html' => '<p>Hi {{ first_name }} at {{ company }}, <a href="{{ unsubscribe_url }}">unsubscribe</a></p>',
    ], $audience))->not->toContain('unknown_merge_tags');
});

test('the preview and send page lists content issues', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $audience = Audience::factory()->for($team)->create();
    Subscriber::factory()->for($audience)->create();
    $email = Email::factory()->for($team)->create([
        'audience_id' => $audience->id,
        'subject' => 'Welcome',
        'preheader' => 'Say hello',
        'html' => '<img src="https://example.com/logo.png"><p>Hi {{ nickname }}</p>',
    ]);

    $this->actingAs($user)
        ->get(route('emails.preview-and-send', [$team, $email]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('contentIssues', 2)
            ->where('contentIssues.0.code', 'unknown_merge_tags')
            ->where('contentIssues.1.code', 'images_without_alt'));
});
