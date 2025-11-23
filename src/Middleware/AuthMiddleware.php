<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuthService;

/**
 * Middleware d'authentification
 *
 * Protège les routes qui nécessitent une authentification
 */
class AuthMiddleware
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Vérifie que l'utilisateur est authentifié
     */
    public function requireAuth(): bool
    {
        if (!$this->authService->isAuthenticated()) {
            // Sauvegarder l'URL demandée pour redirection après login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/dashboard';

            // Rediriger vers la page de connexion
            header('Location: /login');
            exit;
        }

        return true;
    }

    /**
     * Vérifie que l'utilisateur est admin
     */
    public function requireAdmin(): bool
    {
        if (!$this->authService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        if (!$this->authService->isAdmin()) {
            // Accès interdit
            http_response_code(403);
            echo "Accès interdit - Droits administrateur requis";
            exit;
        }

        return true;
    }

    /**
     * Vérifie que l'utilisateur n'est PAS connecté (pour pages login/register)
     */
    public function requireGuest(): bool
    {
        if ($this->authService->isAuthenticated()) {
            // Déjà connecté, rediriger vers le dashboard
            header('Location: /dashboard');
            exit;
        }

        return true;
    }

    /**
     * Vérifie le token CSRF
     */
    public function verifyCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

        if (!$this->authService->verifyCsrfToken($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

        return true;
    }
}
