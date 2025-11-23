<?php

declare(strict_types=1);

namespace App;

use App\Controller\AuthController;
use App\Controller\UserController;
use App\Service\AuthService;
use App\Service\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use PDO;

/**
 * Routeur V2 pour l'authentification
 *
 * Gère les routes d'authentification et les routes protégées
 */
class Router
{
    private $twig;
    private PDO $db;
    private array $config;
    private AuthService $authService;
    private AuthMiddleware $authMiddleware;
    private RateLimitMiddleware $rateLimitMiddleware;

    public function __construct($twig, PDO $db, array $config)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->config = $config;
        $this->authService = new AuthService($db, $config);
        $this->authMiddleware = new AuthMiddleware($this->authService);
        $this->rateLimitMiddleware = new RateLimitMiddleware($config);
    }

    /**
     * Gère une requête
     *
     * Retourne true si la route a été gérée, false sinon
     */
    public function handle(string $uri, string $method): bool
    {
        // Démarrer la session
        $this->authService->startSession();

        // Ajouter l'utilisateur connecté aux variables globales Twig
        if ($this->authService->isAuthenticated()) {
            $this->twig->addGlobal('current_user', $this->authService->getCurrentUser());
            $this->twig->addGlobal('is_authenticated', true);
            $this->twig->addGlobal('is_admin', $this->authService->isAdmin());
        } else {
            $this->twig->addGlobal('is_authenticated', false);
            $this->twig->addGlobal('is_admin', false);
        }

        // Routes publiques (guest only)
        if ($uri === '/login' && $method === 'GET') {
            $controller = new AuthController($this->twig, $this->db, $this->config);
            $controller->showLogin();
            return true;
        }

        if ($uri === '/login' && $method === 'POST') {
            $controller = new AuthController($this->twig, $this->db, $this->config);
            $controller->processLogin();
            return true;
        }

        if ($uri === '/register' && $method === 'GET') {
            $controller = new AuthController($this->twig, $this->db, $this->config);
            $controller->showRegister();
            return true;
        }

        if ($uri === '/register' && $method === 'POST') {
            $controller = new AuthController($this->twig, $this->db, $this->config);
            $controller->processRegister();
            return true;
        }

        // Route de déconnexion
        if ($uri === '/logout') {
            $controller = new AuthController($this->twig, $this->db, $this->config);
            $controller->logout();
            return true;
        }

        // Routes protégées (authentification requise)
        if ($uri === '/profile' && $method === 'GET') {
            $this->authMiddleware->requireAuth();
            $controller = new AuthController($this->twig, $this->db, $this->config);
            $controller->showProfile();
            return true;
        }

        if ($uri === '/profile/change-password' && $method === 'POST') {
            $this->authMiddleware->requireAuth();
            $controller = new AuthController($this->twig, $this->db, $this->config);
            $controller->changePassword();
            return true;
        }

        // Routes admin
        if (preg_match('#^/admin/users#', $uri)) {
            $this->authMiddleware->requireAdmin();
            $controller = new UserController($this->twig, $this->db, $this->config);

            // /admin/users
            if ($uri === '/admin/users' && $method === 'GET') {
                $controller->index();
                return true;
            }

            // /admin/users/create
            if ($uri === '/admin/users/create' && $method === 'GET') {
                $controller->create();
                return true;
            }

            if ($uri === '/admin/users/create' && $method === 'POST') {
                $controller->store();
                return true;
            }

            // /admin/users/edit/{id}
            if (preg_match('#^/admin/users/edit/(\d+)$#', $uri, $matches)) {
                $id = (int) $matches[1];
                if ($method === 'GET') {
                    $controller->edit($id);
                    return true;
                } elseif ($method === 'POST') {
                    $controller->update($id);
                    return true;
                }
            }

            // /admin/users/delete/{id}
            if (preg_match('#^/admin/users/delete/(\d+)$#', $uri, $matches) && $method === 'POST') {
                $id = (int) $matches[1];
                $controller->delete($id);
                return true;
            }

            // /admin/users/toggle-status/{id}
            if (preg_match('#^/admin/users/toggle-status/(\d+)$#', $uri, $matches) && $method === 'POST') {
                $id = (int) $matches[1];
                $controller->toggleStatus($id);
                return true;
            }
        }

        // Route non gérée par ce routeur
        return false;
    }

    /**
     * Protège une route existante (à appeler depuis index.php)
     */
    public function protectRoute(): void
    {
        $this->authService->startSession();
        $this->authMiddleware->requireAuth();
    }

    /**
     * Protège une route admin (à appeler depuis index.php)
     */
    public function protectAdminRoute(): void
    {
        $this->authService->startSession();
        $this->authMiddleware->requireAdmin();
    }
}
