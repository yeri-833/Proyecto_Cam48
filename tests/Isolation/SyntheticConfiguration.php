<?php

namespace Tests\Isolation;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\ServiceProvider;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Monolog\Handler\NullHandler;
use Tighten\Ziggy\ZiggyServiceProvider;

final class SyntheticConfiguration
{
    /** @return array<string, array<string, mixed>> */
    public static function make(string $root, string $runtime): array
    {
        return [
            'app' => [
                'name' => 'FamilyLock Synthetic Tests',
                'env' => 'testing',
                'debug' => false,
                'url' => 'http://localhost',
                'timezone' => 'UTC',
                'locale' => 'en',
                'fallback_locale' => 'en',
                'faker_locale' => 'en_US',
                'key' => '0123456789abcdef0123456789abcdef',
                'cipher' => 'AES-256-CBC',
                'previous_keys' => [],
                'maintenance' => ['driver' => 'file'],
                'providers' => array_merge(ServiceProvider::defaultProviders()->toArray(), [
                    AppServiceProvider::class, InertiaServiceProvider::class, ZiggyServiceProvider::class,
                ]),
                'aliases' => [],
            ],
            'auth' => [
                'defaults' => ['guard' => 'web', 'passwords' => 'users'],
                'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
                'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]],
                'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]],
                'password_timeout' => 10800,
            ],
            'database' => [
                'default' => 'sqlite',
                'connections' => ['sqlite' => [
                    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
                ]],
            ],
            'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array', 'serialize' => false]], 'prefix' => 'synthetic'],
            'session' => [
                'driver' => 'array', 'lifetime' => 120, 'lottery' => [2, 100], 'expire_on_close' => false, 'encrypt' => false,
                'files' => $runtime.'/storage/sessions', 'cookie' => 'synthetic_session', 'path' => '/',
                'domain' => null, 'secure' => false, 'http_only' => true, 'same_site' => 'lax',
            ],
            'hashing' => ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4, 'verify' => true]],
            'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']], 'from' => ['address' => 'synthetic@cam48.test', 'name' => 'Synthetic']],
            'queue' => ['default' => 'null', 'connections' => ['null' => ['driver' => 'null']], 'failed' => ['driver' => 'null']],
            'filesystems' => [
                'default' => 'local',
                'disks' => [
                    'local' => ['driver' => 'local', 'root' => $runtime.'/storage/app/private', 'throw' => true],
                    'public' => ['driver' => 'local', 'root' => $runtime.'/storage/app/public', 'visibility' => 'public', 'throw' => true],
                ],
                'links' => [],
            ],
            'view' => ['paths' => [$root.'/resources/views'], 'compiled' => $runtime.'/views'],
            'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
            'broadcasting' => ['default' => 'null', 'connections' => ['null' => ['driver' => 'null']]],
            'inertia' => [
                'ssr' => ['enabled' => false],
                'ensure_pages_exist' => false,
                'page_paths' => [$root.'/resources/js/Pages'],
                'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
                'testing' => [
                    'ensure_pages_exist' => true,
                    'page_paths' => [$root.'/resources/js/Pages'],
                    'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
                ],
                'history' => ['encrypt' => false],
            ],
            'ziggy' => ['skip-route-function' => true],
            'telegram' => ['bot_token' => '', 'bot_username' => 'synthetic_bot', 'channel_id' => ''],
            'whatsapp' => ['api_url' => '', 'api_token' => ''],
            'boost' => ['enabled' => false],
            'services' => [],
        ];
    }
}
