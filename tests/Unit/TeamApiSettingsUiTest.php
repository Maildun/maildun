<?php

test('api key creation and one-time secret use separate dialogs', function () {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/api.tsx');

    expect($page)->toBeString()
        ->toContain('const [createKeyOpen, setCreateKeyOpen] = useState(false)')
        ->toContain('onClick={() => setCreateKeyOpen(true)}')
        ->toContain('<Dialog open={createKeyOpen} onOpenChange={setCreateKeyOpen}>')
        ->toContain('<DialogTitle>Create API key</DialogTitle>')
        ->toContain('open={newApiKey !== null}')
        ->toContain('This API key is shown only once')
        ->toContain('You will not be able to view it again.')
        ->toContain('setNewApiKey(null)')
        ->toContain('Copy API key')
        ->not->toContain('<AlertAction>');
});

test('api key settings omit embedded documentation and identifiers', function () {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/api.tsx');

    expect($page)->toBeString()
        ->toMatch('/onClick=\{\(\) => setCreateKeyOpen\(true\)\}\s*>\s*Create API key/')
        ->not->toContain('title="API reference"')
        ->not->toContain('title="Team identifiers"')
        ->toContain('Key01Icon')
        ->not->toContain('ShieldKeyIcon')
        ->not->toContain('MailSend01Icon')
        ->not->toContain('UserAdd01Icon')
        ->not->toContain('UserRemove01Icon');
});
