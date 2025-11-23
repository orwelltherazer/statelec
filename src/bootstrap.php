<?php

declare(strict_types=1);

/**
 * Bootstrap de l'application Statelec V2
 *
 * Ce fichier initialise l'environnement et les services de base de l'application.
 * Il doit être inclus au début de chaque point d'entrée (index.php, cron, API, etc.)
 */

// Autoload Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Charger la configuration
$config = require __DIR__ . '/../config/app.php';

// Définir le timezone
date_default_timezone_set($config['app']['timezone']);

// Gérer les erreurs en fonction de l'environnement
if ($config['app']['environment'] === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Initialiser le logger
if ($config['logging']['enabled']) {
    \App\Service\Logger::init($config);

    // Log le démarrage de l'application
    \App\Service\Logger::info('Application started', [
        'environment' => $config['app']['environment'],
        'version' => $config['app']['version'],
        'php_version' => PHP_VERSION,
    ]);
}

// Configurer les sessions si activées (sera utilisé en Phase 2)
if ($config['features']['authentication'] ?? false) {
    ini_set('session.cookie_httponly', (string) $config['security']['session']['cookie_httponly']);
    ini_set('session.cookie_secure', (string) $config['security']['session']['cookie_secure']);
    ini_set('session.cookie_samesite', $config['security']['session']['cookie_samesite']);
    ini_set('session.gc_maxlifetime', (string) $config['security']['session']['lifetime']);

    session_name($config['security']['session']['cookie_name']);
}

// Définir les constantes de l'application
define('APP_VERSION', $config['app']['version']);
define('APP_ENV', $config['app']['environment']);
define('APP_DEBUG', $config['app']['debug']);
define('APP_ROOT', $config['paths']['root']);
define('APP_PUBLIC', $config['paths']['public']);

// Gestionnaire d'erreurs personnalisé
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($config) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorType = match ($errno) {
        E_ERROR, E_USER_ERROR => 'ERROR',
        E_WARNING, E_USER_WARNING => 'WARNING',
        E_NOTICE, E_USER_NOTICE => 'NOTICE',
        E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
        default => 'UNKNOWN',
    };

    if ($config['logging']['enabled']) {
        \App\Service\Logger::error("PHP $errorType: $errstr", [
            'file' => $errfile,
            'line' => $errline,
            'type' => $errorType,
        ]);
    }

    // En production, ne pas afficher l'erreur
    if ($config['app']['environment'] === 'production') {
        return true;
    }

    return false;
});

// Gestionnaire d'exceptions non capturées
set_exception_handler(function (Throwable $exception) use ($config) {
    if ($config['logging']['enabled']) {
        \App\Service\Logger::critical('Uncaught exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    // En production, afficher une page d'erreur générique
    if ($config['app']['environment'] === 'production') {
        http_response_code(500);
        if (php_sapi_name() !== 'cli') {
            echo "Une erreur est survenue. Veuillez réessayer plus tard.";
        }
        exit(1);
    } else {
        // En développement, afficher les détails
        http_response_code(500);
        if (php_sapi_name() !== 'cli') {
            echo "<h1>Erreur non gérée</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>Fichier:</strong> {$exception->getFile()}:{$exception->getLine()}</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        } else {
            echo "Erreur: {$exception->getMessage()}\n";
            echo "Fichier: {$exception->getFile()}:{$exception->getLine()}\n";
        }
        exit(1);
    }
});

// Retourner la configuration pour utilisation dans l'application
return $config;
