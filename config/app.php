<?php

declare(strict_types=1);

/**
 * Configuration principale de l'application Statelec V2
 *
 * Ce fichier centralise toutes les configurations de l'application.
 * Les valeurs sensibles doivent être stockées dans .env
 */

return [
    /**
     * Configuration générale de l'application
     */
    'app' => [
        'name' => 'Statelec V2',
        'version' => '2.0.0',
        'environment' => getenv('APP_ENV') ?: 'production',
        'debug' => getenv('APP_DEBUG') === 'true',
        'timezone' => getenv('TIMEZONE') ?: 'Europe/Paris',
        'locale' => 'fr_FR',
        'url' => getenv('APP_URL') ?: 'http://localhost',
    ],

    /**
     * Configuration base de données
     */
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'statelec',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    /**
     * Configuration logging
     */
    'logging' => [
        'enabled' => true,
        'level' => getenv('LOG_LEVEL') ?: 'info', // debug, info, warning, error, critical
        'path' => __DIR__ . '/../logs/app.log',
        'max_files' => 30,
        'channel' => 'statelec',
    ],

    /**
     * Configuration cache
     */
    'cache' => [
        'enabled' => getenv('CACHE_ENABLED') === 'true',
        'driver' => getenv('CACHE_DRIVER') ?: 'file', // file, redis, apcu
        'ttl' => [
            'default' => 3600, // 1 heure
            'statistics' => 3600, // 1 heure
            'charts' => 1800, // 30 minutes
            'settings' => 7200, // 2 heures
        ],
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => 0,
        ],
        'file' => [
            'path' => __DIR__ . '/../cache',
        ],
    ],

    /**
     * Configuration sécurité
     */
    'security' => [
        'api_key' => getenv('API_KEY'),
        'session' => [
            'lifetime' => 7200, // 2 heures
            'cookie_name' => 'statelec_session',
            'cookie_httponly' => true,
            'cookie_secure' => getenv('APP_ENV') === 'production',
            'cookie_samesite' => 'Lax',
        ],
        'csrf' => [
            'enabled' => true,
            'token_name' => 'csrf_token',
        ],
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 60,
            'window_seconds' => 60,
        ],
        'password' => [
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_special' => false,
        ],
    ],

    /**
     * Configuration API
     */
    'api' => [
        'version' => 'v1',
        'prefix' => '/api/v1',
        'rate_limit' => [
            'max_requests' => 100,
            'window_seconds' => 60,
        ],
        'pagination' => [
            'default_limit' => 50,
            'max_limit' => 1000,
        ],
    ],

    /**
     * Configuration alertes
     */
    'alerts' => [
        'enabled' => true,
        'email' => [
            'enabled' => getenv('ALERT_EMAIL_ENABLED') === 'true',
            'from' => getenv('ALERT_EMAIL_FROM') ?: 'noreply@statelec.local',
            'to' => getenv('ALERT_EMAIL_TO') ?: 'admin@statelec.local',
        ],
        'webhook' => [
            'enabled' => getenv('ALERT_WEBHOOK_ENABLED') === 'true',
            'url' => getenv('ALERT_WEBHOOK_URL') ?: null,
        ],
        'thresholds' => [
            'power_high' => 3000, // W
            'power_critical' => 6000, // W
            'daily_high' => 20, // kWh
            'daily_critical' => 30, // kWh
            'base_load_warning' => 150, // W
        ],
        'cooldown_minutes' => 60, // Temps minimum entre 2 alertes du même type
    ],

    /**
     * Configuration tarifs électriques
     */
    'pricing' => [
        'default' => [
            'hc' => 0.1740, // €/kWh heures creuses
            'hp' => 0.2228, // €/kWh heures pleines
            'abonnement' => 12.44, // €/mois
        ],
    ],

    /**
     * Configuration analytics
     */
    'analytics' => [
        'anomaly_detection' => [
            'enabled' => true,
            'sensitivity' => 'medium', // low, medium, high
            'min_deviation_percent' => 15,
        ],
        'device_detection' => [
            'enabled' => true,
            'confidence_threshold' => 70, // %
        ],
        'forecasting' => [
            'enabled' => true,
            'algorithm' => 'moving_average', // moving_average, exponential_smoothing
            'window_days' => 7,
        ],
    ],

    /**
     * Configuration temps réel
     */
    'realtime' => [
        'enabled' => true,
        'sse' => [
            'enabled' => true,
            'heartbeat_seconds' => 30,
        ],
        'polling_interval_seconds' => 30,
    ],

    /**
     * Configuration export
     */
    'export' => [
        'enabled' => true,
        'formats' => ['csv', 'excel', 'pdf', 'json'],
        'max_range_days' => 365,
        'path' => __DIR__ . '/../storage/exports',
        'auto_cleanup_days' => 7,
    ],

    /**
     * Configuration PWA
     */
    'pwa' => [
        'enabled' => false, // Sera activé en Phase 10
        'name' => 'Statelec',
        'short_name' => 'Statelec',
        'theme_color' => '#3b82f6',
        'background_color' => '#ffffff',
    ],

    /**
     * Feature flags - Activation progressive des fonctionnalités
     */
    'features' => [
        'authentication' => true, // Phase 2 - ACTIVÉ
        'advanced_dashboard' => false, // Phase 3-5
        'realtime_updates' => false, // Phase 7
        'advanced_alerts' => false, // Phase 8
        'api_v1' => false, // Phase 9
        'export' => false, // Phase 9
        'pwa' => false, // Phase 10
        'multi_user' => true, // Phase 2 - ACTIVÉ
    ],

    /**
     * Paths
     */
    'paths' => [
        'root' => dirname(__DIR__),
        'public' => dirname(__DIR__) . '/public',
        'storage' => dirname(__DIR__) . '/storage',
        'logs' => dirname(__DIR__) . '/logs',
        'cache' => dirname(__DIR__) . '/cache',
        'templates' => dirname(__DIR__) . '/templates',
    ],
];
