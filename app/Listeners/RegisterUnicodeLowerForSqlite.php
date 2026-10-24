<?php

namespace App\Listeners;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\SQLiteConnection;
use Pdo\Sqlite;

class RegisterUnicodeLowerForSqlite
{
    /**
     * Replace SQLite's ASCII-only LOWER() with a Unicode-aware one.
     *
     * Searches and segment rules compare LOWER(column) with a term lowercased
     * by mb_strtolower(). Postgres lowercases accented and non-Latin capitals
     * too, but SQLite's built-in LOWER() leaves them alone, so local and test
     * databases missed matches such as "Élodie" for "élodie".
     */
    public function handle(ConnectionEstablished $event): void
    {
        if (! $event->connection instanceof SQLiteConnection) {
            return;
        }

        $pdo = $event->connection->getPdo();

        if (! $pdo instanceof Sqlite) {
            return;
        }

        $pdo->createFunction(
            'lower',
            fn (mixed $value): ?string => $value === null ? null : mb_strtolower((string) $value),
            1,
            Sqlite::DETERMINISTIC,
        );
    }
}
