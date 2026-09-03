<?php

namespace App\Actions\Media;

use App\Models\Media;

class DeleteTeamMedia
{
    public function handle(Media $media): void
    {
        $media->delete();
    }
}
