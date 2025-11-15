<?php

declare(strict_types=1);

namespace Statelec\Service;

use PDOException;

/**
 * Gestionnaire d'erreurs centralisé
 */
class ErrorHandler
{
    private static array $errors = [];
    private static bool $hasDbError = false;

    /**
     * Ajoute une erreur à la liste
     */
    public static function addError(string $type, string $message, array $context = []): void
    {
        self::$errors[] = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($type === 'database') {
            self::$hasDbError = true;
        }

        // En production, logger dans un fichier
        error_log("[$type] $message " . json_encode($context));
    }

    /**
     * Vérifie s'il y a eu une erreur de base de données
     */
    public static function hasDatabaseError(): bool
    {
        return self::$hasDbError;
    }

    /**
     * Récupère toutes les erreurs
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Récupère les erreurs par type
     */
    public static function getErrorsByType(string $type): array
    {
        return array_filter(self::$errors, fn($error) => $error['type'] === $type);
    }

    /**
     * Formate les erreurs pour l'affichage
     */
    public static function formatErrorsForDisplay(): array
    {
        $formatted = [];
        foreach (self::$errors as $error) {
            $formatted[] = [
                'type' => $error['type'],
                'message' => self::getSafeErrorMessage($error['type'], $error['message']),
                'timestamp' => $error['timestamp']
            ];
        }
        return $formatted;
    }

    /**
     * Retourne un message d'erreur sécurisé (sans détails techniques)
     */
    private static function getSafeErrorMessage(string $type, string $message): string
    {
        switch ($type) {
            case 'database':
                if (str_contains($message, 'SQLSTATE[HY000] [2002]')) {
                    return 'Impossible de se connecter à la base de données. Vérifiez que le serveur MySQL est démarré et accessible.';
                }
                if (str_contains($message, 'SQLSTATE[HY000] [1049]')) {
                    return 'Base de données introuvable. Vérifiez le nom de la base de données.';
                }
                if (str_contains($message, 'SQLSTATE[HY000] [1045]')) {
                    return 'Identifiants de base de données incorrects. Vérifiez votre configuration.';
                }
                return 'Erreur de base de données. Contactez l\'administrateur.';
                
            case 'api':
                return 'Erreur lors de l\'appel à l\'API. Vérifiez votre connexion.';
                
            case 'validation':
                return $message; // Les erreurs de validation sont déjà safe
                
            default:
                return 'Une erreur est survenue. Veuillez réessayer plus tard.';
        }
    }

    /**
     * Efface toutes les erreurs
     */
    public static function clear(): void
    {
        self::$errors = [];
        self::$hasDbError = false;
    }

    /**
     * Gère une exception PDO
     */
    public static function handlePDOException(PDOException $e, array $context = []): void
    {
        self::addError('database', $e->getMessage(), array_merge($context, [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]));
    }

    /**
     * Vérifie si l'application est en mode dégradé
     */
    public static function isDegradedMode(): bool
    {
        return self::$hasDbError;
    }

    /**
     * Retourne le statut de l'application pour les APIs
     */
    public static function getApplicationStatus(): array
    {
        return [
            'status' => self::$hasDbError ? 'degraded' : 'ok',
            'errors' => self::formatErrorsForDisplay(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}