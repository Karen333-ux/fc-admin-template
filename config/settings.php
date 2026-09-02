<?php

declare(strict_types=1);

use Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;
use Spatie\LaravelSettings\SettingsRepositories\RedisSettingsRepository;
use Src\Contexts\Settings\Domain\Settings\AppearanceSettings;

return [

    /*
     * Each settings class used in your application must be registered, you can
     * put them (manually) here.
     */
    'settings' => [
        // الشريحة دي فيها المظهر بس. باقي الكلاسات (general/storage/mail/
        // notification/security) في `docs/05` بند ٢ وبتتضاف مع شرايحها.
        AppearanceSettings::class,
    ],

    /*
     * The path where the settings classes will be created.
     */
    'setting_class_path' => app_path('Settings'),

    /*
     * In these directories settings migrations will be stored and ran when migrating. A settings
     * migration created via the make:settings-migration command will be stored in the first path or
     * a custom defined path when running the command.
     */
    'migrations_paths' => [
        // جوّه السياق زي باقي هجراته — مش database_path (docs/01)
        base_path('src/Contexts/Settings/Database/SettingsMigrations'),
    ],

    /*
     * When no repository was set for a settings class the following repository
     * will be used for loading and saving settings.
     */
    'default_repository' => 'database',

    /*
     * Settings will be stored and loaded from these repositories.
     */
    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => null,
            'connection' => null,
        ],
        'redis' => [
            'type' => RedisSettingsRepository::class,
            'connection' => null,
            'prefix' => null,
        ],
    ],

    /*
     * The encoder and decoder will determine how settings are stored and
     * retrieved in the database. By default, `json_encode` and `json_decode`
     * are used.
     */
    'encoder' => null,
    'decoder' => null,

    /*
     * The contents of settings classes can be cached through your application,
     * settings will be stored within a provided Laravel store and can have an
     * additional prefix.
     */
    'cache' => [
        'enabled' => (bool) env('SETTINGS_CACHE_ENABLED', true),
        'store' => env('SETTINGS_CACHE_STORE'),
        'prefix' => 'fc_settings',
        'ttl' => null,

        /*
         * When enabled, uses Laravel's memoized cache driver (requires Laravel 12.9+)
         * to keep resolved values in memory during a single request.
         */
        'memo' => env('SETTINGS_CACHE_MEMO', false),
    ],

    /*
     * These global casts will be automatically used whenever a property within
     * your settings class isn't a default PHP type.
     */
    'global_casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        DateTimeZone::class => DateTimeZoneCast::class,
        //        Spatie\DataTransferObject\DataTransferObject::class => Spatie\LaravelSettings\SettingsCasts\DtoCast::class,
        // Spatie\LaravelData مش متثبّت — الكاست بتاعه اتشال بدل ما يشاور
        // على كلاس مش موجود. يترجّع مع الباكدج لو اتضاف.
    ],

    /*
     * The package will look for settings in these paths and automatically
     * register them.
     */
    'auto_discover_settings' => [
        // فاضي عن قصد: التسجيل صريح في 'settings' فوق. الاكتشاف التلقائي
        // بيدوّر في app/ وكلاساتنا في src/ (docs/01).
    ],

    /*
     * Automatically discovered settings classes can be cached, so they don't
     * need to be searched each time the application boots up.
     */
    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
