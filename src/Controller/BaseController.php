<?php

declare(strict_types=1);

namespace App\Controller;

use PDO;
use Twig\Environment;

/**
 * Contrôleur de base
 *
 * Classe parente pour tous les contrôleurs avec méthodes utilitaires
 */
class BaseController
{
    protected Environment $twig;
    protected PDO $db;
    protected array $config;

    public function __construct($twig, $db, $config)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Renvoie une réponse JSON
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirige vers une URL
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    /**
     * Récupère un paramètre POST
     */
    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Récupère un paramètre GET
     */
    protected function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Valide des champs requis
     */
    protected function validateRequired(array $fields): ?string
    {
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                return "Le champ '$field' est requis";
            }
        }
        return null;
    }

    /**
     * Nettoie une chaîne
     */
    protected function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Flash message
     */
    protected function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Récupère et supprime les flash messages
     */
    protected function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}
