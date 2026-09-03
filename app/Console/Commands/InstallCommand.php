<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\StorageBackend;
use App\Models\User;
use App\Services\EnvironmentFile;
use App\Services\InstallationState;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InstallSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

#[Signature('app:install
    {--admin-name= : Display name for the first administrator}
    {--admin-email= : Email address for the first administrator}
    {--admin-password= : Password for the first administrator}
    {--registration= : Whether strangers may sign up themselves: "open" or "closed"}
    {--storage= : Hand "local" or "s3" straight to storage:configure}
    {--skip-storage : Leave the storage backend as it is}
    {--demo : Also seed the demo workspace, which is never appropriate on real data}
    {--force : Skip the production confirmation}')]
#[Description('Prepare a deployment: app key, migrations, baseline data, sign-up policy, and the first administrator')]
class InstallCommand extends Command
{
    use ConfirmableTrait, PasswordValidationRules, ProfileValidationRules;

    /**
     * Values that belong in .env but could not be written there.
     *
     * @var array<string, string>
     */
    private array $unwritableEnvironment = [];

    /**
     * Things the operator still has to do, printed as the closing summary.
     *
     * @var list<string>
     */
    private array $outstanding = [];

    /**
     * The single entry point for a fresh install and for deploy scripts.
     *
     * Every step is idempotent, so running this on each deploy converges the
     * environment instead of re-doing work. Options cover everything the
     * prompts ask for, which is what makes --no-interaction usable on Forge
     * and Laravel Cloud.
     */
    public function handle(EnvironmentFile $environment, InstallationState $installation): int
    {
        $choice = $this->option('registration');
        $registration = is_string($choice) ? $this->parseRegistrationChoice($choice) : null;

        if (is_string($choice) && $registration === null) {
            $this->components->error('--registration must be either "open" or "closed".');

            return self::FAILURE;
        }

        $this->components->info('Installing '.config('app.name').'.');

        $this->prepareEnvironmentFile($environment);
        $this->ensureApplicationKey($environment);

        if (! $this->confirmToProceed('Application In Production', fn (): bool => $this->laravel->isProduction() && $this->isAlreadyInstalled())) {
            return self::FAILURE;
        }

        if (! $this->migrate()) {
            return self::FAILURE;
        }

        $this->seedBaseline();
        $this->configureRegistration($environment, $registration);
        $this->createAdministrator();

        if (User::query()->exists()) {
            $installation->markComplete();
        } elseif ($browserUrl = $installation->browserUrl()) {
            $this->outstanding[] = 'Create the first administrator in your browser (valid for 24 hours): '.$browserUrl;
        } else {
            $this->outstanding[] = 'Set APP_KEY in the hosting dashboard, then re-run app:install to generate the secure browser setup link.';
        }

        $this->configureStorage();

        if (! $this->seedDemoData()) {
            return self::FAILURE;
        }

        $this->refreshCachedConfiguration();
        $this->summarize();

        return self::SUCCESS;
    }

    /**
     * Read --registration up front so a typo fails before anything is touched.
     *
     * @return bool|null Null for a value that means neither open nor closed.
     */
    private function parseRegistrationChoice(string $choice): ?bool
    {
        return match (mb_strtolower($choice)) {
            'open', 'true', '1', 'yes', 'on' => true,
            'closed', 'false', '0', 'no', 'off' => false,
            default => null,
        };
    }

    /**
     * Laravel Cloud injects real environment variables and serves no .env, so
     * an existing APP_KEY without a file means the environment is managed
     * elsewhere and must be left alone.
     */
    private function prepareEnvironmentFile(EnvironmentFile $environment): void
    {
        if ($environment->exists()) {
            $this->components->twoColumnDetail('.env', '<fg=green>present</>');

            return;
        }

        if ($environment->get('APP_KEY') !== '' || ! $environment->isWritable()) {
            $this->components->twoColumnDetail('.env', '<fg=yellow>managed by the platform</>');

            return;
        }

        $environment->ensureExists();

        $this->components->twoColumnDetail('.env', '<fg=green>created from .env.example</>');
    }

    private function ensureApplicationKey(EnvironmentFile $environment): void
    {
        if ((string) config('app.key') !== '') {
            $this->components->twoColumnDetail('Application key', '<fg=green>set</>');

            return;
        }

        if ($environment->isWritable()) {
            $this->callSilent('key:generate', ['--force' => true]);
            $this->components->twoColumnDetail('Application key', '<fg=green>generated</>');

            return;
        }

        /*
         * Nothing is encrypted yet on a deployment without a key, so printing
         * the generated value is safe and is the only way to hand it to a
         * dashboard-managed environment.
         */
        $this->call('key:generate', ['--show' => true]);
        $this->outstanding[] = 'Store the application key printed above as APP_KEY.';
    }

    /**
     * Determine whether this deployment is already carrying real data.
     */
    private function isAlreadyInstalled(): bool
    {
        try {
            return Schema::hasTable('users') && User::query()->exists();
        } catch (Throwable) {
            /* No reachable database means there is nothing to protect yet. */
            return false;
        }
    }

    private function migrate(): bool
    {
        $exitCode = $this->call('migrate', ['--force' => true]);

        if ($exitCode !== self::SUCCESS) {
            $this->components->error('Migrations failed, so the install stopped here.');
        }

        return $exitCode === self::SUCCESS;
    }

    private function seedBaseline(): void
    {
        $this->callSilent('db:seed', ['--class' => InstallSeeder::class, '--force' => true]);

        $this->components->twoColumnDetail('Baseline data', '<fg=green>seeded</>');
    }

    /**
     * Persist the sign-up policy, leaving it untouched when nothing said so.
     */
    private function configureRegistration(EnvironmentFile $environment, ?bool $open): void
    {
        $current = (bool) config('fortify.registration_open');

        $open ??= $this->input->isInteractive()
            ? $this->confirm('Let anyone create an account? Invited teammates can always sign up either way.', $current)
            : $current;

        $this->writeEnvironment($environment, ['REGISTRATION_ENABLED' => $open ? 'true' : 'false']);
        config()->set('fortify.registration_open', $open);

        $this->components->twoColumnDetail(
            'Registration',
            $open ? '<fg=green>open to anyone</>' : '<fg=yellow>invitation only</>',
        );
    }

    /**
     * Create the first administrator, or leave an existing one alone.
     */
    private function createAdministrator(): void
    {
        $email = $this->option('admin-email');
        $email = is_string($email) ? $email : null;

        if ($email !== null && User::query()->where('email', $email)->exists()) {
            $this->components->twoColumnDetail('Administrator', $email.' <fg=green>already exists</>');

            return;
        }

        if ($email === null && $this->isAlreadyInstalled()) {
            $this->components->twoColumnDetail('Administrator', '<fg=green>already exists</>');

            return;
        }

        if ($email === null && ! $this->input->isInteractive()) {
            $this->components->twoColumnDetail('Administrator', '<fg=yellow>not created</>');

            return;
        }

        /*
         * Retrying only helps when the details came from a prompt. Replaying
         * the same rejected --admin-* options would just repeat the error.
         */
        $attempts = $this->input->isInteractive() && ! $this->hasAdministratorOptions() ? 3 : 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($this->storeAdministrator($this->askForAdministrator($email))) {
                return;
            }

            $email = null;
        }

        $this->components->warn('No administrator was created from the supplied details.');
    }

    private function hasAdministratorOptions(): bool
    {
        return is_string($this->option('admin-name'))
            || is_string($this->option('admin-email'))
            || is_string($this->option('admin-password'));
    }

    /**
     * @return array{name: string, email: string, password: string, password_confirmation: string}
     */
    private function askForAdministrator(?string $email): array
    {
        $name = $this->option('admin-name');
        $password = $this->option('admin-password');

        $name = is_string($name) ? $name : ($this->input->isInteractive() ? (string) $this->ask('Administrator name') : '');
        $email ??= $this->input->isInteractive() ? (string) $this->ask('Administrator email') : '';

        if (! is_string($password)) {
            $password = $this->input->isInteractive() ? (string) $this->secret('Administrator password') : '';

            return [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => (string) $this->secret('Confirm the password'),
            ];
        }

        /*
         * A password supplied on the command line has nothing to confirm it
         * against, so it stands in for its own confirmation.
         */
        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    /**
     * @param  array{name: string, email: string, password: string, password_confirmation: string}  $input
     */
    private function storeAdministrator(array $input): bool
    {
        $validator = Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            $this->components->error('The administrator could not be created:');
            $this->components->bulletList($validator->errors()->all());

            return false;
        }

        DB::transaction(function () use ($input): void {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            /*
             * There is no working mailer yet on a fresh box, so the operator
             * could never click a verification link to get in.
             */
            $user->forceFill(['email_verified_at' => now()])->save();

            app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);
        });

        $this->components->twoColumnDetail('Administrator', $input['email'].' <fg=green>created</>');

        return true;
    }

    private function configureStorage(): void
    {
        if ($this->option('skip-storage')) {
            return;
        }

        $backend = $this->option('storage');

        if (is_string($backend)) {
            if ($this->call('storage:configure', ['--backend' => $backend]) !== self::SUCCESS) {
                $this->outstanding[] = 'Finish the storage setup with: php artisan storage:configure';
            }

            return;
        }

        if ($this->input->isInteractive()) {
            $this->call('storage:configure');

            return;
        }

        /*
         * Nothing was asked for, so only the piece a local deployment cannot
         * work without gets done. Anything S3-shaped needs credentials that
         * only the operator has.
         */
        if (StorageBackend::current() === StorageBackend::Local && ! file_exists(public_path('storage'))) {
            $this->callSilent('storage:link');
        }

        $this->components->twoColumnDetail('Storage', StorageBackend::current()->label().' <fg=green>unchanged</>');
    }

    private function seedDemoData(): bool
    {
        if (! $this->option('demo')) {
            return true;
        }

        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->components->error('Refusing to seed demo data in production. Pass --force if that is really what you want.');

            return false;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        return true;
    }

    /**
     * .env is only read at boot, so a cached config would keep serving the old
     * values after this command rewrote them.
     */
    private function refreshCachedConfiguration(): void
    {
        if (! $this->laravel->configurationIsCached()) {
            return;
        }

        $this->callSilent('optimize');

        $this->components->twoColumnDetail('Cached configuration', '<fg=green>rebuilt</>');
    }

    /**
     * Write values to .env, or collect them when the platform owns them.
     *
     * @param  array<string, string>  $values
     */
    private function writeEnvironment(EnvironmentFile $environment, array $values): void
    {
        if (! $environment->isWritable()) {
            $this->unwritableEnvironment += $values;
            $environment->applyToRuntime($values);

            return;
        }

        $environment->put($values);
        $environment->applyToRuntime($values);
    }

    private function summarize(): void
    {
        $this->newLine();
        $this->components->info(
            User::query()->exists()
                ? config('app.name').' is installed.'
                : config('app.name').' is ready for browser setup.',
        );
        $this->components->twoColumnDetail('URL', (string) config('app.url'));

        if ($this->unwritableEnvironment !== []) {
            $this->components->warn('Set these in your hosting dashboard, because .env is not writable here:');
            $this->components->bulletList(array_map(
                fn (string $key, string $value): string => $key.'='.$value,
                array_keys($this->unwritableEnvironment),
                $this->unwritableEnvironment,
            ));
        }

        foreach ($this->outstanding as $item) {
            $this->components->warn($item);
        }

        $this->components->bulletList([
            'Keep a queue worker alive: php artisan horizon (Forge daemon, or the worker Laravel Cloud already runs).',
            'Run the scheduler every minute: php artisan schedule:run.',
            'Each workspace picks its own mail provider in Settings → Email delivery.',
        ]);
    }
}
