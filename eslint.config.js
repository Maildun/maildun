import js from '@eslint/js';
import react from '@eslint-react/eslint-plugin';
import stylistic from '@stylistic/eslint-plugin';
import prettier from 'eslint-config-prettier/flat';
import { createTypeScriptImportResolver } from 'eslint-import-resolver-typescript';
import { createNodeResolver, importX } from 'eslint-plugin-import-x';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

const controlStatements = [
    'if',
    'return',
    'for',
    'while',
    'do',
    'switch',
    'try',
    'throw',
];
const paddingAroundControl = [
    ...controlStatements.flatMap((stmt) => [
        { blankLine: 'always', prev: '*', next: stmt },
        { blankLine: 'always', prev: stmt, next: '*' },
    ]),
];

/**
 * Hook rules @eslint-react also ships. eslint-plugin-react-hooks stays the
 * source of truth for these, so the duplicates are switched off.
 */
const reactHookDuplicates = Object.fromEntries(
    [
        'error-boundaries',
        'exhaustive-deps',
        'globals',
        'immutability',
        'purity',
        'refs',
        'rules-of-hooks',
        'set-state-in-effect',
        'set-state-in-render',
        'static-components',
        'unsupported-syntax',
        'use-memo',
    ].map((rule) => [`@eslint-react/${rule}`, 'off']),
);

/** @type {import('eslint').Linter.Config[]} */
export default [
    js.configs.recommended,
    reactHooks.configs.flat['recommended-latest'],
    ...typescript.configs.recommended,
    {
        ...react.configs['recommended-typescript'],
        languageOptions: {
            ...react.configs['recommended-typescript'].languageOptions,
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            ...react.configs['recommended-typescript'].rules,
            ...reactHookDuplicates,
            '@eslint-react/dom-no-dangerously-set-innerhtml': 'off',
            '@eslint-react/naming-convention-ref-name': 'off',
            '@eslint-react/no-array-index-key': 'off',
            '@eslint-react/no-context-provider': 'off',
            '@eslint-react/no-use-context': 'off',
            '@eslint-react/use-state': 'off',
            '@eslint-react/web-api-no-leaked-event-listener': 'off',
            '@eslint-react/web-api-no-leaked-timeout': 'off',
        },
    },
    {
        plugins: {
            'import-x': importX,
        },
        settings: {
            'import-x/resolver-next': [
                createTypeScriptImportResolver({
                    alwaysTryTypes: true,
                    project: './tsconfig.json',
                }),
                createNodeResolver(),
            ],
        },
        rules: {
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/consistent-type-imports': [
                'error',
                {
                    prefer: 'type-imports',
                    fixStyle: 'separate-type-imports',
                },
            ],
            'import-x/order': [
                'error',
                {
                    groups: [
                        'builtin',
                        'external',
                        'internal',
                        'parent',
                        'sibling',
                        'index',
                    ],
                    alphabetize: { order: 'asc', caseInsensitive: true },
                },
            ],
            'import-x/consistent-type-specifier-style': [
                'error',
                'prefer-top-level',
            ],
        },
    },
    {
        plugins: {
            '@stylistic': stylistic,
        },
        rules: {
            '@stylistic/brace-style': ['error', '1tbs', { allowSingleLine: false }],
            '@stylistic/padding-line-between-statements': [
                'error',
                ...paddingAroundControl,
            ],
        },
    },
    {
        ignores: [
            'vendor',
            'node_modules',
            '.claude/worktrees/**',
            'public',
            'bootstrap/ssr',
            'tailwind.config.js',
            'vite.config.ts',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
    },
    prettier,
    {
        plugins: {
            '@stylistic': stylistic,
        },
        rules: {
            curly: ['error', 'all'],
            '@stylistic/brace-style': ['error', '1tbs', { allowSingleLine: false }],
        },
    },
];
