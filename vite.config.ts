import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv } from 'vite';
import type { Plugin } from 'vite';

const hugeiconsIconPackages = {
    'free-stroke-rounded': '@hugeicons/core-free-icons',
    'pro-stroke-rounded': '@hugeicons-pro/core-stroke-rounded',
    'pro-stroke-standard': '@hugeicons-pro/core-stroke-standard',
    'pro-solid-rounded': '@hugeicons-pro/core-solid-rounded',
    'pro-solid-standard': '@hugeicons-pro/core-solid-standard',
} as const;

function resolveHugeiconsIconPackage(iconStyle: string): string {
    const iconPackage =
        hugeiconsIconPackages[iconStyle as keyof typeof hugeiconsIconPackages];

    if (!iconPackage) {
        throw new Error(
            `Unsupported HUGEICONS_ICON_STYLE "${iconStyle}". Choose one of: ${Object.keys(hugeiconsIconPackages).join(', ')}.`,
        );
    }

    return iconPackage;
}

const hugeiconsImport =
    /import\s*\{([^}]*)\}\s*from\s*['"](@hugeicons\/core-free-icons|@hugeicons-pro\/core-[a-z-]+)['"];?/g;

/**
 * Rewrites `import { FooIcon } from '@hugeicons/…'` into one import per icon
 * file. The packages' index re-exports ~6,000 icons, and every barrel import
 * made the build parse all of them before tree shaking dropped the unused
 * ones. Covers the app and @maildun/email-builder's dist, which bundles its
 * own copy of the free set. Build only: the dev server keeps the barrel.
 */
function hugeiconsPerIconImports(freeIconPackage: string): Plugin {
    const iconFilesByPackage = new Map<string, Map<string, string>>();

    function iconFile(
        iconPackage: string,
        iconName: string,
        importer: string,
    ): string {
        const packageJson = createRequire(importer).resolve(
            `${iconPackage}/package.json`,
        );
        let iconFiles = iconFilesByPackage.get(packageJson);

        if (!iconFiles) {
            iconFiles = new Map();
            const index = readFileSync(
                join(dirname(packageJson), 'dist/esm/index.js'),
                'utf8',
            );

            for (const [, names, file] of index.matchAll(
                /export \{([^}]*)\} from '\.\/([^']+)\.js'/g,
            )) {
                for (const name of names.split(',')) {
                    iconFiles.set(
                        name
                            .trim()
                            .split(/\s+as\s+/)
                            .pop()!,
                        file,
                    );
                }
            }

            iconFilesByPackage.set(packageJson, iconFiles);
        }

        const file = iconFiles.get(iconName);

        if (!file) {
            throw new Error(`${iconPackage} has no icon named ${iconName}.`);
        }

        return file;
    }

    return {
        name: 'hugeicons-per-icon-imports',
        apply: 'build',
        enforce: 'pre',
        transform(code, id) {
            const isAppSource = !id.includes('/node_modules/');
            const isEmailBuilder = id.includes(
                '/node_modules/@maildun/email-builder/dist/',
            );

            if (
                (!isAppSource && !isEmailBuilder) ||
                !/\.[jt]sx?$/.test(id) ||
                !code.includes('@hugeicons')
            ) {
                return null;
            }

            const rewritten = code.replace(
                hugeiconsImport,
                (statement: string, specifiers: string, source: string) => {
                    const iconPackage =
                        source === '@hugeicons/core-free-icons'
                            ? freeIconPackage
                            : source;
                    const imports = specifiers
                        .split(',')
                        .map((specifier) => specifier.trim())
                        .filter(
                            (specifier) =>
                                specifier !== '' &&
                                !specifier.startsWith('type '),
                        )
                        .map((specifier) => {
                            const [iconName, localName = iconName] =
                                specifier.split(/\s+as\s+/);

                            return `import ${localName} from '${iconPackage}/${iconFile(iconPackage, iconName, id)}';`;
                        });

                    return (
                        imports.join(' ') +
                        '\n'.repeat(statement.split('\n').length - 1)
                    );
                },
            );

            return rewritten === code ? null : { code: rewritten, map: null };
        },
    };
}

export default defineConfig(({ mode }) => {
    const { HUGEICONS_ICON_STYLE: iconStyle = 'free-stroke-rounded' } = loadEnv(
        mode,
        process.cwd(),
        'HUGEICONS_ICON_',
    );
    const iconPackage = resolveHugeiconsIconPackage(iconStyle);

    return {
        build: {
            /**
             * Keep flag-icons SVGs as standalone assets so a campaign report
             * downloads only the flags it renders instead of inlining a few
             * hundred base64 flags into the bundle.
             */
            assetsInlineLimit: (filePath: string) =>
                filePath.includes('flag-icons/flags/') ? false : undefined,
        },
        resolve: {
            alias: [
                {
                    find: /^@hugeicons\/core-free-icons$/,
                    replacement: iconPackage,
                },
            ],
        },
        ssr: {
            noExternal: ['@hugeicons/core-free-icons', iconPackage],
        },
        plugins: [
            hugeiconsPerIconImports(iconPackage),
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            inertia(),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
        ],
    };
});
