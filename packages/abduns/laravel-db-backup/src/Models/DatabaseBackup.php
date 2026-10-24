<?php

namespace Abduns\DbBackup\Models;

use Abduns\DbBackup\Enums\BackupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One backup run: queued, running, or finished with a file on a disk or an error.
 *
 * @property int $id
 * @property string $uuid
 * @property BackupStatus $status
 * @property string $connection
 * @property string|null $label
 * @property string|null $driver
 * @property string $disk
 * @property string|null $path
 * @property string|null $file_name
 * @property int|null $size
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DatabaseBackup extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Keep the integer key; the uuid is the public identifier.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Who asked for the backup. Null for scheduled and console runs.
     *
     * @return MorphTo<Model, $this>
     */
    public function triggeredBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSucceeded(Builder $query): void
    {
        $query->where('status', BackupStatus::Succeeded);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUnfinished(Builder $query): void
    {
        $query->whereIn('status', [BackupStatus::Pending, BackupStatus::Running]);
    }

    public function isSucceeded(): bool
    {
        return $this->status === BackupStatus::Succeeded;
    }

    public function durationInSeconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at, absolute: true);
    }
}
