<?php

namespace App\Actions\Media;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class SearchReadyTeamMedia
{
    /**
     * Ready images in the team's library, newest first, optionally matching a
     * file name or alt text. Processing and failed uploads have no URL yet.
     *
     * @return Collection<int, Media>
     */
    public function handle(Team $team, string $search = '', int $limit = 20): Collection
    {
        $search = trim($search);
        $pattern = '%'.addcslashes($search, '%_\\').'%';

        return $team->media()
            ->with(['category', 'tags'])
            ->where('status', MediaStatus::Ready)
            ->whereNotNull('path')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', $pattern)
                ->orWhereLike('alt', $pattern)))
            ->latest()
            ->limit($limit)
            ->get();
    }
}
