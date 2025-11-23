<?php

declare(strict_types=1);

namespace App\Service;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;

/**
 * Service de logging centralisé pour Statelec V2
 *
 * Utilise Monolog pour gérer les logs de l'application avec rotation automatique.
 * Supporte différents niveaux de log et canaux.
 */
class Logger
{
    private static ?MonologLogger $instance = null;
    private static array $config = [];

    /**
     * Initialise le logger avec la configuration
     */
    public static function init(array $config = []): void
    {
        self::$config = $config;
    }

    /**
     * Récupère l'instance du logger (singleton)
     */
    private static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            $config = self::$config['logging'] ?? [];

            $channel = $config['channel'] ?? 'statelec';
            $logPath = $config['path'] ?? __DIR__ . '/../../logs/app.log';
            $maxFiles = $config['max_files'] ?? 30;
            $level = self::parseLevel($config['level'] ?? 'info');

            self::$instance = new MonologLogger($channel);

            // Handler avec rotation quotidienne
            $handler = new RotatingFileHandler($logPath, $maxFiles, $level);

            // Format personnalisé
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                "Y-m-d H:i:s",
                true,
                true
            );
            $handler->setFormatter($formatter);

            self::$instance->pushHandler($handler);

            // En mode debug, ajouter aussi un handler console
            if (($config['debug'] ?? false) && php_sapi_name() === 'cli') {
                $consoleHandler = new StreamHandler('php://stdout', Level::Debug);
                $consoleHandler->setFormatter($formatter);
                self::$instance->pushHandler($consoleHandler);
            }
        }

        return self::$instance;
    }

    /**
     * Convertit le niveau de log string en Level Monolog
     */
    private static function parseLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Info,
        };
    }

    /**
     * Log debug
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    /**
     * Log info
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    /**
     * Log notice
     */
    public static function notice(string $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }

    /**
     * Log warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    /**
     * Log error
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    /**
     * Log critical
     */
    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    /**
     * Log alert
     */
    public static function alert(string $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }

    /**
     * Log emergency
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }

    /**
     * Log une requête API
     */
    public static function logApiRequest(string $method, string $endpoint, array $params = [], ?int $userId = null): void
    {
        self::info('API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }

    /**
     * Log une erreur API
     */
    public static function logApiError(string $endpoint, string $error, int $statusCode, array $context = []): void
    {
        self::error('API Error', [
            'endpoint' => $endpoint,
            'error' => $error,
            'status_code' => $statusCode,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }

    /**
     * Log une tentative de connexion
     */
    public static function logLogin(string $email, bool $success, ?string $reason = null): void
    {
        $level = $success ? 'info' : 'warning';

        self::$level('Login attempt', [
            'email' => $email,
            'success' => $success,
            'reason' => $reason,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    }

    /**
     * Log une anomalie détectée
     */
    public static function logAnomaly(string $type, string $description, array $data = []): void
    {
        self::warning('Anomaly detected', [
            'type' => $type,
            'description' => $description,
            'data' => $data,
        ]);
    }

    /**
     * Log une alerte envoyée
     */
    public static function logAlert(string $type, string $severity, string $message, array $context = []): void
    {
        self::notice('Alert sent', [
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * Log une erreur de base de données
     */
    public static function logDatabaseError(string $query, string $error, array $params = []): void
    {
        self::error('Database error', [
            'query' => $query,
            'error' => $error,
            'params' => $params,
        ]);
    }

    /**
     * Log une performance (temps d'exécution)
     */
    public static function logPerformance(string $operation, float $executionTime, array $metadata = []): void
    {
        self::info('Performance', [
            'operation' => $operation,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'metadata' => $metadata,
        ]);
    }
}
