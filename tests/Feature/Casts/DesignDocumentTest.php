<?php

use App\Models\Email;
use App\Models\EmailTemplate;

test('a design keeps its empty objects when it is read and stored again', function () {
    $template = EmailTemplate::factory()->create([
        'design' => EmailTemplate::builderDesign([
            'footer' => ['type' => 'container', 'props' => new stdClass, 'children' => []],
        ]),
    ]);

    $email = Email::factory()->builder()->create(['design' => $template->refresh()->design]);

    expect($email->refresh()->getRawOriginal('design'))
        ->toContain('"footer":{"type":"container","props":{}')
        ->not->toContain('"props":[]');
});
