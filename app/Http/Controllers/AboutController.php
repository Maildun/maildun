<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    /**
     * The author attribution that term 1 of the additional terms in LICENSE
     * requires the Appropriate Legal Notices to keep.
     */
    public const string COPYRIGHT_NOTICE = 'Maildun, Copyright (C) 2026 Dunn';

    /**
     * Where `vite build` writes the licenses of the third-party code it
     * bundles, relative to the public directory. See build.license in
     * vite.config.ts.
     */
    public const string BUNDLED_LICENSES_PATH = 'build/third-party-licenses.txt';

    /**
     * Show Maildun's Appropriate Legal Notices: the copyright notice, the lack
     * of warranty, the right to convey the work under the license, and where
     * to read that license and the source.
     */
    public function __invoke(): Response
    {
        return Inertia::render('about', [
            'version' => config('version.current'),
            'copyright' => self::COPYRIGHT_NOTICE,
            'sourceUrl' => config('attribution.source_url'),
            'bundledLicensesUrl' => file_exists(public_path(self::BUNDLED_LICENSES_PATH))
                ? asset(self::BUNDLED_LICENSES_PATH)
                : null,
        ]);
    }
}
