<?php

namespace App\Http\Controllers;

use App\Services\DiceBearAvatarGenerator;
use Illuminate\Http\Response;

class AvatarController extends Controller
{
    public function __construct(private readonly DiceBearAvatarGenerator $avatarGenerator) {}

    public function __invoke(string $style, string $seed): Response
    {
        return response($this->avatarGenerator->svg($style, $seed), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age='.DiceBearAvatarGenerator::CACHE_SECONDS,
        ]);
    }
}
