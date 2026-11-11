<?php

declare(strict_types=1);
use Laravel\Boost\Mcp\Tools\DatabaseConnections;
use Laravel\Boost\Mcp\Tools\DatabaseQuery;
use Laravel\Boost\Mcp\Tools\DatabaseSchema;
use Laravel\Boost\Mcp\Tools\Tinker;

return [

    /*
    |--------------------------------------------------------------------------
    | Boost Master Switch
    |--------------------------------------------------------------------------
    */
    'enabled' => env('BOOST_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Boost Project Rules
    |--------------------------------------------------------------------------
    */
    'rules' => [
        'enabled' => env('BOOST_RULES_ENABLED', true),
        'scoped_guidelines' => env('BOOST_RULES_SCOPED_GUIDELINES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Guidelines
    |--------------------------------------------------------------------------
    */
    'guidelines' => [
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Skills
    |--------------------------------------------------------------------------
    */
    'skills' => [
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Boost Executables Paths
    |--------------------------------------------------------------------------
    */
    'executable_paths' => [
        'php' => env('BOOST_PHP_EXECUTABLE_PATH', 'php'),
        'composer' => env('BOOST_COMPOSER_EXECUTABLE_PATH', 'composer'),
        'npm' => env('BOOST_NPM_EXECUTABLE_PATH', 'npm'),
        'vendor_bin' => env('BOOST_VENDOR_BIN_EXECUTABLE_PATH'),
        'current_directory' => env('BOOST_CURRENT_DIRECTORY_EXECUTABLE_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Boost Browser Logs Watcher
    |--------------------------------------------------------------------------
    */
    'browser_logs_watcher' => env('BOOST_BROWSER_LOGS_WATCHER', false),

    'browser_log_levels' => explode(',', env('BOOST_BROWSER_LOG_LEVELS', 'error,warning')),

    /*
    |--------------------------------------------------------------------------
    | MCP Tool Isolation & MariaDB Database Protection
    |--------------------------------------------------------------------------
    | Para proteger estrictamente la base institucional (proyecto_colegio),
    | se excluyen las herramientas de consulta SQL, esquema y Tinker.
    */
    'mcp' => [
        'tools' => [
            'exclude' => [
                DatabaseConnections::class,
                DatabaseQuery::class,
                DatabaseSchema::class,
                Tinker::class,
            ],
            'include' => [],
        ],
    ],

];
