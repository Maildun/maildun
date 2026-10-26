<?php

namespace App\Actions\Media;

use App\Concerns\SearchesText;
use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class SearchReadyTeamMedia
{
    use SearchesText;

    /**
     * Ready images in the team's library, newest first, optionally matching a
     * file name or alt text. Processing and failed uploads have no URL yet.
     *
     * @return Collection<int, Media>
     */
    public function handle(Team $team, string $search = '', int $limit = 20): Collection
    {
        $search = trim($search);

        return $team->media()
            ->with(['category', 'tags'])
            ->where('status', MediaStatus::Ready)
            ->whereNotNull('path')
            ->when($search !== '', fn ($query) => $this->whereAnyColumnContains($query, ['name', 'alt'], $search))
            ->latest()
            ->limit($limit)
            ->get();
    }
}
