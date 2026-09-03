<?php

test('brand theme preview sits outside the form so it cannot submit the team PATCH', function () {
    $root = dirname(__DIR__, 2);
    $source = file_get_contents($root.'/resources/js/pages/teams/theme.tsx');

    expect($source)->toBeString();

    $formClose = strpos((string) $source, '</Form>');
    $previewPanel = strpos((string) $source, 'title="Preview"');
    $previewInput = strpos((string) $source, 'id="theme-preview-email"');

    expect($formClose)->not->toBeFalse();
    expect($previewPanel)->not->toBeFalse();
    expect($previewInput)->not->toBeFalse()
        ->and($formClose)->toBeLessThan($previewPanel)
        ->and($formClose)->toBeLessThan($previewInput);
});

test('brand theme save button stays inside the form', function () {
    $root = dirname(__DIR__, 2);
    $source = (string) file_get_contents($root.'/resources/js/pages/teams/theme.tsx');

    $formClose = strpos($source, '</Form>');
    $saveButton = strpos($source, 'data-test="save-team-theme"');

    expect($saveButton)->not->toBeFalse()
        ->and($formClose)->not->toBeFalse()
        ->and($saveButton)->toBeLessThan($formClose);
});

test('delete account copy reflects team handover instead of wiping every team', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/delete-user.tsx');

    expect($source)->toBeString()
        ->toContain('Teams you own will pass to another admin or member.')
        ->toContain('longest-standing admin or member')
        ->toContain('your personal team will be permanently removed')
        ->not->toContain('all of its resources and data will also be permanently deleted');
});

test('settings forms toast on save, guard unsaved changes, and focus the first invalid field', function () {
    $root = dirname(__DIR__, 2);

    foreach (['settings/profile', 'teams/edit', 'teams/email', 'teams/sender', 'teams/theme'] as $page) {
        $source = file_get_contents($root."/resources/js/pages/{$page}.tsx");

        expect($source)->toBeString()
            ->toContain('UnsavedChangesGuard')
            ->toContain("title: 'Changes saved.'")
            ->toContain('focusFirstInvalidField')
            ->toContain('Save changes');

        if ($page !== 'teams/sender') {
            expect($source)->toContain('w-full sm:w-auto');
        }
    }
});

test('email builder save stays inside the editor form', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/email.tsx');

    $formClose = strpos($source, '</Form>');
    $saveButton = strpos($source, 'data-test="save-email-settings"');

    expect($saveButton)->not->toBeFalse()
        ->and($formClose)->not->toBeFalse()
        ->and($saveButton)->toBeLessThan($formClose)
        ->and($source)->toContain('tabIndex=')
        ->toContain('role="radiogroup"')
        ->not->toContain('email_from_address')
        ->not->toContain('title="Sender"');
});

test('email editor save button tracks the hidden input with local state', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/email.tsx');

    expect($source)->toBeString()
        ->toContain('editor !== settings.email_editor')
        ->toContain('const formIsDirty = isDirty || hasEditorChanges')
        ->toContain('<UnsavedChangesGuard isDirty={formIsDirty} />')
        ->toContain('processing || !formIsDirty')
        ->not->toContain('disabled={processing || !isDirty}');
});

test('sender management adds addresses in a dialog and keeps detail updates separate', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/sender.tsx');

    expect($source)->toContain('store.form.post(team.slug)')
        ->toContain('update.form.patch')
        ->toContain('data-test="add-sender-button"')
        ->toContain('data-test="add-sender-submit"')
        ->toContain('<DialogContent>')
        ->toContain('<DialogTitle>Add sender</DialogTitle>')
        ->toContain('data-test="sender-row"')
        ->toContain('data-test="sender-actions"')
        ->toContain('<DialogTitle>Edit sender</DialogTitle>')
        ->toContain('data-test="save-sender-settings"')
        ->toContain('A verified domain authorizes it immediately; otherwise we send a verification email.')
        ->toContain('Addresses are immutable')
        ->toContain('Resend verification')
        ->toContain('Use as default')
        ->toContain('data-test="remove-sender-confirm"')
        ->not->toContain('data-test="sender-tag"');
});

test('security settings drop the page heading and title the 2FA and passkeys panels', function () {
    $security = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/settings/security.tsx');
    $twoFactor = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/manage-two-factor.tsx');
    $passkeys = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/manage-passkeys.tsx');

    expect($security)->toBeString()
        ->not->toContain("from '@/components/heading'")
        ->toContain('title="Two-factor authentication"')
        ->toContain('title="Passkeys"')
        ->toContain('gap-8');

    expect($twoFactor)->toBeString()
        ->not->toContain("from '@/components/heading'");

    expect($passkeys)->toBeString()
        ->toContain("from '@/components/ui/empty'")
        ->toContain('AuthorizedIcon')
        ->not->toContain('Key01Icon')
        ->not->toContain("from '@/components/heading'");
});

test('member actions keep role editing in a dialog and group password recovery tools', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/teams/members.tsx');
    $editMemberModal = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/edit-member-modal.tsx');

    expect($source)->toBeString()
        ->toContain('data-test="member-role-badge"')
        ->toContain('data-test="member-actions"')
        ->toContain('<DropdownMenuContent align="end" className="w-max">')
        ->toContain('data-test="edit-member-button"')
        ->toContain('data-test="member-reset-password-button"')
        ->toContain('data-test="member-generate-password-button"')
        ->toContain('data-test="member-send-reset-email-button"')
        ->toContain("from '@/components/ui/button-group'")
        ->toContain('data-test="delete-member-button"')
        ->toContain('data-test="invitation-cancel-button"')
        ->toContain('Reset password')
        ->toContain('Send reset email')
        ->toContain('<DialogContent className="w-fit">')
        ->toContain('<ButtonGroup aria-label="Password reset actions">')
        ->toContain('variant="outline"')
        ->toContain('ResetPasswordIcon')
        ->toContain('size="icon"')
        ->toContain('aria-label="Generate new password"')
        ->toContain('Generate a new password?')
        ->toContain('This password is shown only once')
        ->toContain('data-test="member-copy-generated-password-button"')
        ->toContain("router.on('flash'")
        ->toContain('variant="secondary"')
        ->toContain('MoreHorizontalIcon')
        ->toContain('text-muted-foreground')
        ->not->toContain('DropdownMenuRadioGroup')
        ->not->toContain('member-role-trigger')
        ->not->toContain('updateMemberRole')
        ->not->toContain('Cancel01Icon')
        ->not->toContain('Copy01Icon')
        ->not->toContain('Copy password')
        ->not->toContain('<ButtonGroupSeparator />')
        ->not->toContain('TooltipProvider')
        ->not->toContain('text-muted-foreground/70');

    expect($editMemberModal)->toBeString()
        ->toContain('Edit workspace member')
        ->toContain('SelectGroup')
        ->toContain('role === member.role')
        ->toContain('updateMember([team.slug, member.id])');
});
