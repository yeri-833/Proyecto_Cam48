<?php

namespace Tests\Isolation;

use App\Console\Commands\SyncTelegramUpdates;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class IsolatedApplication
{
    public static function make(): Application
    {
        IsolationGuard::assertRuntime();
        $root = IsolationGuard::projectPath();
        $runtime = IsolationGuard::runtimePath().'/case-'.bin2hex(random_bytes(8));
        foreach (['environment', 'bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/testing/disks', 'storage/sessions', 'views', 'public', 'database'] as $directory) {
            if (! mkdir($runtime.'/'.$directory, 0700, true) && ! is_dir($runtime.'/'.$directory)) {
                throw new RuntimeException('ISOLATION_APPLICATION_PATH_CREATION_FAILED');
            }
        }

        $app = IsolatedLaravelApplication::configure(basePath: $runtime)
            ->withRouting(web: $root.'/routes/web.php', api: $root.'/routes/api.php', commands: $root.'/routes/console.php', health: '/up')
            ->withCommands([SyncTelegramUpdates::class])
            ->withMiddleware(function (Middleware $middleware): void {
                $middleware->web(append: [
                    HandleInertiaRequests::class,
                    AddLinkHeadersForPreloadedAssets::class,
                ]);
            })
            ->withExceptions()
            ->create();
        $app->useAppPath($root.'/app');
        $app->useEnvironmentPath($runtime.'/environment');
        $app->loadEnvironmentFrom('absent.synthetic.environment');
        $app->useBootstrapPath($runtime.'/bootstrap');
        $app->useStoragePath($runtime.'/storage');
        $app->usePublicPath($runtime.'/public');
        $app->useDatabasePath($runtime.'/database');

        LoadConfiguration::alwaysUse(fn (): array => SyntheticConfiguration::make($root, $runtime));
        $manifest = new PackageManifest(new Filesystem, $root, $runtime.'/bootstrap/cache/packages.php');
        $manifest->manifest = [];
        $app->instance(PackageManifest::class, $manifest);

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $application): void {
            IsolationGuard::assertDatabaseConfiguration($application['config']->get('database'));
        });
        $app->beforeBootstrapping(RegisterProviders::class, function (Application $application): void {
            $application->bind('db.connector.sqlite', fn (): MemoryOnlyConnector => new MemoryOnlyConnector);
            $application->resolving('db', function (mixed $manager): void {
                if (! $manager instanceof MemoryOnlyDatabaseManager) {
                    throw new RuntimeException('ISOLATION_EARLY_DATABASE_RESOLUTION_REJECTED');
                }
            });
        });
        $app->afterBootstrapping(RegisterProviders::class, function (Application $application): void {
            $application->singleton('db', fn (Application $container): MemoryOnlyDatabaseManager => new MemoryOnlyDatabaseManager($container, $container['db.factory']));
            $application->bind('db.connector.sqlite', fn (): MemoryOnlyConnector => new MemoryOnlyConnector);
            $application['events']->listen(CommandStarting::class, function (CommandStarting $event): void {
                if (str_starts_with($event->command, 'migrate') || in_array($event->command, ['db:wipe', 'db:seed', 'schema:dump'], true)) {
                    throw new RuntimeException('ISOLATION_DATABASE_COMMAND_FORBIDDEN');
                }
            });
        });

        $app->booting(function (Application $application): void {
            Http::swap(new IsolatedHttpFactory($application['events']));
            Http::preventStrayRequests();
            Mail::fake();
            Queue::fake();
            Notification::fake();
            Storage::fake('local');
            Storage::fake('public');
        });

        return $app;
    }
}
