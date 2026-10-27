/**
 * Maildun's Appropriate Legal Notices, as section 0 of the GNU Affero General
 * Public License defines them. Term 1 of the additional terms in LICENSE
 * requires them to keep the author attribution passed in as `copyright`. A
 * modified version may move, restyle, or reword this page, but it must keep
 * that attribution. See LICENSE for the terms that apply to this file.
 */
import { ArrowUpRight01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import { license, notices } from '@/routes/about';

const PRODUCT_NAME = 'Maildun';

type Props = {
    version: string;
    copyright: string;
    sourceUrl: string;
    bundledLicensesUrl: string | null;
};

type NoticeLink = {
    label: string;
    href: string;
    test: string;
};

export default function About({
    version,
    copyright,
    sourceUrl,
    bundledLicensesUrl,
}: Props) {
    const { name } = usePage().props;
    const links: NoticeLink[] = [
        {
            label: 'Read the license',
            href: license.url(),
            test: 'about-license-link',
        },
        {
            label: 'Source code',
            href: sourceUrl,
            test: 'about-source-link',
        },
        {
            label: 'Third-party notices',
            href: notices.url(),
            test: 'about-notices-link',
        },
    ];

    if (bundledLicensesUrl) {
        links.push({
            label: 'Licenses of bundled frontend packages',
            href: bundledLicensesUrl,
            test: 'about-bundled-licenses-link',
        });
    }

    return (
        <>
            <Head title={`About ${PRODUCT_NAME}`} />
            <main className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6">
                <Link href={home()} aria-label={name}>
                    <AppLogoIcon className="size-8" />
                </Link>
                <Card className="w-full max-w-md" data-test="about-card">
                    <CardHeader>
                        <CardTitle>{PRODUCT_NAME}</CardTitle>
                        <CardDescription data-test="about-version">
                            Version {version}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="font-medium" data-test="about-copyright">
                            {copyright}
                        </p>
                        <p className="text-muted-foreground">
                            {PRODUCT_NAME} is free software, licensed under the
                            GNU Affero General Public License, version 3, with
                            additional terms. You may redistribute and modify it
                            under those terms.
                        </p>
                        <p className="text-muted-foreground">
                            {PRODUCT_NAME} comes with NO WARRANTY, to the extent
                            permitted by law.
                        </p>
                        <ul className="space-y-2">
                            {links.map(({ label, href, test }) => (
                                <li key={test}>
                                    <a
                                        href={href}
                                        target="_blank"
                                        rel="noreferrer"
                                        data-test={test}
                                        className="group inline-flex items-center gap-1 text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    >
                                        {label}
                                        <HugeiconsIcon
                                            icon={ArrowUpRight01Icon}
                                            className="size-3.5 text-muted-foreground motion-safe:transition-transform motion-safe:duration-200 motion-safe:group-hover:translate-x-0.5 motion-safe:group-hover:-translate-y-0.5"
                                        />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

About.layout = null;
