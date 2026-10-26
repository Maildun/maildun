<?php

namespace Abduns\DbBackup;

use Carbon\CarbonImmutable;

/**
 * A backup file found on the disk. Restoring works from the files themselves,
 * not from the database_backups table, which a new server does not have yet.
 */
final readonly class RestorableBackup
{
    /**
     * Matches "{connection}-{Y-m-d-His}[-{label}].{extension}".
     */
    private const FILE_NAME = '/^(?<connection>.+)-(?<stamp>\d{4}-\d{2}-\d{2}-\d{6})(?:-(?<label>[a-z][a-z0-9-]*))?\.(?<extension>dump|sql\.gz|sqlite\.gz)$/';

    public function __construct(
        public string $path,
        public string $fileName,
        public string $connection,
        public CarbonImmutable $takenAt,
        public string $extension,
        public ?string $label,
    ) {}

    public static function fromPath(string $path): ?self
    {
        $fileName = basename($path);

        if (preg_match(self::FILE_NAME, $fileName, $matches) !== 1) {
            return null;
        }

        $takenAt = CarbonImmutable::createFromFormat('Y-m-d-His', $matches['stamp'], config('app.timezone'));

        if ($takenAt === null) {
            return null;
        }

        return new self(
            path: $path,
            fileName: $fileName,
            connection: $matches['connection'],
            takenAt: $takenAt,
            extension: $matches['extension'],
            label: $matches['label'] === '' ? null : $matches['label'],
        );
    }
}
