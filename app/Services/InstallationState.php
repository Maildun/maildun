<?php

namespace App\Services;

use App\Enums\StorageBackend;
use App\Enums\TeamPermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Spatie\Permission\Models\Permission;
use Throwable;

class InstallationState
{
    private const int SingletonId = 1;

    public function __construct(
        private StorageBackendMigrator $storageMigrator,
        private MasterSupervisorRepository $masterSupervisors,
    ) {}

    /**
     * Determine whether an administrator has already claimed this deployment.
     */
    public function isComplete(): bool
    {
        try {
            if (Schema::hasTable('installations') && DB::table('installations')
                ->where('id', self::SingletonId)
                ->whereNotNull('completed_at')
                ->exists()) {
                return true;
            }

            return Schema::hasTable('users') && User::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Report the infrastructure checks required before creating an administrator.
     *
     * @return list<array{key: string, label: string, description: string, ready: bool}>
     */
    public function checks(): array
    {
        $schemaReady = $this->passes(function (): bool {
            foreach (['installations', 'users', 'teams', 'team_members', 'permissions', 'roles', 'sessions'] as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        });

        return [
            [
                'key' => 'app-key',
                'label' => 'Application key',
                'description' => 'Encryption is configured for this deployment.',
                'ready' => filled(config('app.key')),
            ],
            [
                'key' => 'database',
                'label' => 'Database schema',
                'description' => 'The database is connected and migrations are current.',
                'ready' => $schemaReady,
            ],
            [
                'key' => 'baseline',
                'label' => 'Application data',
                'description' => 'Required workspace permissions and starter data are installed.',
                'ready' => $schemaReady && $this->passes(fn (): bool => Permission::query()
                    ->whereIn('name', array_column(TeamPermission::cases(), 'value'))
                    ->where('guard_name', 'web')
                    ->count() === count(TeamPermission::cases())),
            ],
            [
                'key' => 'cache',
                'label' => 'Cache connection',
                'description' => 'The configured cache service is reachable.',
                'ready' => $this->passes(function (): bool {
                    $key = 'maildun.installation.readiness.'.Str::uuid();

                    Cache::put($key, true, 10);
                    $ready = Cache::get($key) === true;
                    Cache::forget($key);

                    return $ready;
                }),
            ],
        ];
    }

    /**
     * Report sanitized deployment diagnostics without exposing infrastructure credentials.
     *
     * @return list<array{key: string, label: string, description: string, status: 'ready'|'failed'|'pending'}>
     */
    public function systemChecks(bool $probeStorage = false): array
    {
        $checks = array_map(
            fn (array $check): array => [
                'key' => $check['key'],
                'label' => $check['label'],
                'description' => $check['description'],
                'status' => $check['ready'] ? 'ready' : 'failed',
            ],
            $this->checks(),
        );

        $queueConnection = (string) config('queue.default');
        $usesRedisQueue = $queueConnection === 'redis';
        $horizonRunning = $usesRedisQueue && $this->passes(function (): bool {
            $masters = $this->masterSupervisors->all();

            return $masters !== [] && collect($masters)->doesntContain(
                fn (object $master): bool => data_get($master, 'status') === 'paused',
            );
        });

        $checks[] = [
            'key' => 'queue',
            'label' => 'Queue connection',
            'description' => $usesRedisQueue
                ? 'Redis is selected for durable background jobs.'
                : 'Set QUEUE_CONNECTION=redis so Maildun background jobs are durable.',
            'status' => $usesRedisQueue ? 'ready' : 'failed',
        ];
        $checks[] = [
            'key' => 'horizon',
            'label' => 'Horizon workers',
            'description' => $horizonRunning
                ? 'Horizon is running and accepting background jobs.'
                : 'Start Horizon under a process manager and confirm Redis is reachable.',
            'status' => $horizonRunning ? 'ready' : 'failed',
        ];

        $backend = StorageBackend::current();
        $missingStorage = $this->storageMigrator->missingConfiguration($backend);
        $storageConfigured = $missingStorage === [];
        $checks[] = [
            'key' => 'storage-configuration',
            'label' => 'Storage configuration',
            'description' => $storageConfigured
                ? $backend->label().' has all required settings.'
                : 'Missing settings: '.implode(', ', $missingStorage).'.',
            'status' => $storageConfigured ? 'ready' : 'failed',
        ];

        if (! $probeStorage) {
            $checks[] = [
                'key' => 'storage-round-trip',
                'label' => 'Storage read and write',
                'description' => 'Run the system test to write, read, and remove a temporary file on both storage roles.',
                'status' => 'pending',
            ];

            return $checks;
        }

        $storageFailures = $storageConfigured
            ? $this->storageMigrator->probe($backend)
            : ['configuration' => 'missing'];
        $storageReady = $storageFailures === [];
        $checks[] = [
            'key' => 'storage-round-trip',
            'label' => 'Storage read and write',
            'description' => $storageReady
                ? 'Temporary files were written, read back, and removed from both storage roles.'
                : 'One or more storage roles rejected the temporary file check.',
            'status' => $storageReady ? 'ready' : 'failed',
        ];

        return $checks;
    }

    public function isReady(): bool
    {
        return collect($this->checks())->every(
            fn (array $check): bool => $check['ready'],
        );
    }

    /**
     * Atomically reserve the only browser installation slot.
     */
    public function claim(): bool
    {
        return DB::table('installations')->insertOrIgnore([
            'id' => self::SingletonId,
            'started_at' => now(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    /**
     * Persist completion independently of deployment filesystems.
     */
    public function markComplete(): void
    {
        $now = now();

        DB::table('installations')->insertOrIgnore([
            'id' => self::SingletonId,
            'started_at' => $now,
            'completed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('installations')
            ->where('id', self::SingletonId)
            ->update([
                'completed_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Generate a host-independent link that may be copied from deployment logs.
     */
    public function browserUrl(): ?string
    {
        if (! filled(config('app.key'))) {
            return null;
        }

        $path = URL::temporarySignedRoute(
            'install.show',
            now()->addDay(),
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$path;
    }

    /**
     * Whether queue workers are picking up background jobs. Only Horizon on
     * Redis reports its supervisors, so any other queue setup is "unknown"
     * rather than guessed at.
     *
     * @return 'running'|'paused'|'stopped'|'unknown'
     */
    public function workerState(): string
    {
        if (config('queue.default') !== 'redis') {
            return 'unknown';
        }

        try {
            $masters = $this->masterSupervisors->all();
        } catch (Throwable) {
            return 'unknown';
        }

        return match (true) {
            $masters === [] => 'stopped',
            collect($masters)->every(fn (object $master): bool => data_get($master, 'status') === 'paused') => 'paused',
            default => 'running',
        };
    }

    private function passes(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
