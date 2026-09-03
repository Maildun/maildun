<?php

test('uses Maildun as the configured application name', function () {
    expect(config('app.name'))->toBe('Maildun');
});
