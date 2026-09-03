<?php

test('auth pages use a centered form layout on a grainy muted background', function () {
    $root = dirname(__DIR__, 2);
    $authLayout = file_get_contents($root.'/resources/js/layouts/auth/auth-simple-layout.tsx');

    expect($authLayout)->toBeString()
        ->toContain('grainy')
        ->toContain('bg-muted/50')
        ->toContain('items-center')
        ->toContain('max-w-sm')
        ->toContain('w-80')
        ->toContain('<AppLogoIcon className="size-7" />')
        ->toContain("const isLogin = component === 'auth/login';")
        ->toContain('hover:text-[#2c8df2]')
        ->toContain('focus-visible:text-[#2c8df2]')
        ->toContain('motion-reduce:transition-none')
        ->toContain('aria-label={name}')
        ->toContain('self-start')
        ->toContain('text-left')
        ->toContain("import { AnimatePresence, motion } from 'motion/react';")
        ->toContain('<AnimatePresence mode="wait" initial={false}>')
        ->toContain('key={component}')
        ->toContain('initial={{ opacity: 0, y: 16 }}')
        ->toContain('animate={{ opacity: 1, y: 0 }}')
        ->toContain('exit={{ opacity: 0, y: -8 }}')
        ->not->toContain("import { SettingsPanel } from '@/components/settings-panel';")
        ->not->toContain('ring-1 ring-border')
        ->not->toContain('data-test="auth-card"')
        ->not->toContain('bg-card/80')
        ->not->toContain('Workspace overview');
});

test('auth forms do not expose passkey controls', function () {
    $root = dirname(__DIR__, 2);
    $login = file_get_contents($root.'/resources/js/pages/auth/login.tsx');
    $confirmPassword = file_get_contents($root.'/resources/js/pages/auth/confirm-password.tsx');

    expect($login)->toBeString()
        ->not->toContain('PasskeyVerify')
        ->not->toContain('passkey');

    expect($confirmPassword)->toBeString()
        ->not->toContain('PasskeyVerify')
        ->not->toContain('passkey');
});

test('login form reveals the password step after email validation', function () {
    $root = dirname(__DIR__, 2);
    $login = file_get_contents($root.'/resources/js/pages/auth/login.tsx');

    expect($login)->toBeString()
        ->toContain('useState(false)')
        ->toContain('reportValidity()')
        ->toContain('Continue with email')
        ->toContain('data-test="continue-with-email-button"')
        ->toContain('data-test="login-password-step"')
        ->toContain('setShowPasswordStep(true)')
        ->toContain('{!showPasswordStep ? (')
        ->toContain("import { motion } from 'motion/react';")
        ->toContain('initial={{ opacity: 0, y: 10 }}')
        ->toContain('animate={{ opacity: 1, y: 0 }}')
        ->toContain('duration: 0.24')
        ->toContain('type="submit"');
});

test('reset password fields use the shared shadcn input styles', function () {
    $root = dirname(__DIR__, 2);
    $resetPassword = file_get_contents(
        $root.'/resources/js/pages/auth/reset-password.tsx',
    );

    expect($resetPassword)->toBeString()
        ->toContain("import { Input } from '@/components/ui/input';")
        ->toContain("import PasswordInput from '@/components/password-input';")
        ->not->toContain('className="mt-1 block w-full"');
});
