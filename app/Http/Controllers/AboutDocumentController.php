<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class AboutDocumentController extends Controller
{
    /**
     * Serve the full license: the additional terms, then the AGPL itself.
     */
    public function license(): Response
    {
        return $this->plainText(base_path('LICENSE'));
    }

    /**
     * Serve the notices and licenses of the third-party material in Maildun.
     */
    public function notices(): Response
    {
        return $this->plainText(base_path('THIRD_PARTY_NOTICES.md'));
    }

    /**
     * Return a file from the repository as plain text, or 404 when a build
     * left it out.
     */
    private function plainText(string $path): Response
    {
        abort_unless(File::isFile($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
