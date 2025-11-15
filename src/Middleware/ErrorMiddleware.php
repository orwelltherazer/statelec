<?php

declare(strict_types=1);

namespace Statelec\Middleware;

use Statelec\Service\ErrorHandler;
use PDOException;

/**
 * Middleware pour gérer les erreurs de manière gracieuse
 */
class ErrorMiddleware
{
    /**
     * Exécute une fonction avec gestion d'erreurs
     */
    public static function safeExecute(callable $callback, array $context = []): mixed
    {
        try {
            return $callback();
        } catch (PDOException $e) {
            ErrorHandler::handlePDOException($e, $context);
            return self::getErrorPageData();
        } catch (Exception $e) {
            ErrorHandler::addError('system', $e->getMessage(), array_merge($context, [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]));
            return self::getErrorPageData();
        }
    }

    /**
     * Retourne les données pour une page d'erreur
     */
    private static function getErrorPageData(): array
    {
        return [
            'page_title' => 'Erreur - Statelec',
            'errors' => ErrorHandler::formatErrorsForDisplay(),
            'is_degraded_mode' => ErrorHandler::isDegradedMode(),
            'theme' => 'light'
        ];
    }

    /**
     * Vérifie si la base de données est accessible
     */
    public static function isDatabaseAccessible(): bool
    {
        try {
            $pdo = \Statelec\Service\Database::getInstance();
            $stmt = $pdo->query("SELECT 1");
            return $stmt->fetchColumn() === 1;
        } catch (PDOException $e) {
            ErrorHandler::handlePDOException($e, ['operation' => 'database_health_check']);
            return false;
        }
    }

    /**
     * Retourne une réponse JSON pour les APIs
     */
    public static function getJsonErrorResponse(string $message = 'Une erreur est survenue'): string
    {
        http_response_code(500);
        
        return json_encode([
            'success' => false,
            'error' => $message,
            'status' => ErrorHandler::getApplicationStatus()
        ]);
    }
}