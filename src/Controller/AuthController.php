<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Service\Logger;
use App\Middleware\RateLimitMiddleware;

/**
 * Contrôleur d'authentification
 *
 * Gère login, logout, register
 */
class AuthController extends BaseController
{
    private AuthService $authService;
    private RateLimitMiddleware $rateLimitMiddleware;

    public function __construct($twig, $db, $config)
    {
        parent::__construct($twig, $db, $config);
        $this->authService = new AuthService($db, $config);
        $this->rateLimitMiddleware = new RateLimitMiddleware($config);
    }

    /**
     * Page de connexion (GET)
     */
    public function showLogin(): void
    {
        $this->authService->startSession();

        // Si déjà connecté, rediriger
        if ($this->authService->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }

        $csrfToken = $this->authService->generateCsrfToken();

        echo $this->twig->render('pages/auth/login.twig', [
            'csrf_token' => $csrfToken,
            'error' => $_SESSION['login_error'] ?? null
        ]);

        unset($_SESSION['login_error']);
    }

    /**
     * Traitement de la connexion (POST)
     */
    public function processLogin(): void
    {
        $this->authService->startSession();

        // Vérifier le rate limiting
        if (!$this->rateLimitMiddleware->checkLoginAttempts()) {
            return; // Le middleware gère la réponse
        }

        // Vérifier le CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!$this->authService->verifyCsrfToken($csrfToken)) {
            $_SESSION['login_error'] = 'Token de sécurité invalide';
            header('Location: /login');
            exit;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email et mot de passe requis';
            header('Location: /login');
            exit;
        }

        $result = $this->authService->login($email, $password, $remember);

        if ($result['success']) {
            // Rediriger vers la page demandée ou le dashboard
            $redirectTo = $_SESSION['redirect_after_login'] ?? '/dashboard';
            unset($_SESSION['redirect_after_login']);

            header("Location: $redirectTo");
            exit;
        } else {
            $_SESSION['login_error'] = $result['message'];
            header('Location: /login');
            exit;
        }
    }

    /**
     * Déconnexion
     */
    public function logout(): void
    {
        $this->authService->startSession();
        $this->authService->logout();

        header('Location: /login');
        exit;
    }

    /**
     * Page d'inscription (GET)
     */
    public function showRegister(): void
    {
        $this->authService->startSession();

        // Si déjà connecté, rediriger
        if ($this->authService->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }

        $csrfToken = $this->authService->generateCsrfToken();

        echo $this->twig->render('pages/auth/register.twig', [
            'csrf_token' => $csrfToken,
            'error' => $_SESSION['register_error'] ?? null,
            'success' => $_SESSION['register_success'] ?? null
        ]);

        unset($_SESSION['register_error'], $_SESSION['register_success']);
    }

    /**
     * Traitement de l'inscription (POST)
     */
    public function processRegister(): void
    {
        $this->authService->startSession();

        // Vérifier le rate limiting
        if (!$this->rateLimitMiddleware->check(10, 3600)) { // 10 inscriptions par heure
            return;
        }

        // Vérifier le CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!$this->authService->verifyCsrfToken($csrfToken)) {
            $_SESSION['register_error'] = 'Token de sécurité invalide';
            header('Location: /register');
            exit;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $name = $_POST['name'] ?? '';

        // Validation
        if (empty($email) || empty($password) || empty($name)) {
            $_SESSION['register_error'] = 'Tous les champs sont requis';
            header('Location: /register');
            exit;
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['register_error'] = 'Les mots de passe ne correspondent pas';
            header('Location: /register');
            exit;
        }

        $result = $this->authService->register($email, $password, $name);

        if ($result['success']) {
            $_SESSION['register_success'] = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
            header('Location: /login');
            exit;
        } else {
            $_SESSION['register_error'] = $result['message'];
            header('Location: /register');
            exit;
        }
    }

    /**
     * Page de profil utilisateur
     */
    public function showProfile(): void
    {
        $this->authService->startSession();

        if (!$this->authService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $user = $this->authService->getCurrentUser();
        $csrfToken = $this->authService->generateCsrfToken();

        echo $this->twig->render('pages/auth/profile.twig', [
            'user' => $user,
            'csrf_token' => $csrfToken,
            'success' => $_SESSION['profile_success'] ?? null,
            'error' => $_SESSION['profile_error'] ?? null
        ]);

        unset($_SESSION['profile_success'], $_SESSION['profile_error']);
    }

    /**
     * Changement de mot de passe
     */
    public function changePassword(): void
    {
        $this->authService->startSession();

        if (!$this->authService->isAuthenticated()) {
            $this->jsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
            return;
        }

        // Vérifier le CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!$this->authService->verifyCsrfToken($csrfToken)) {
            $_SESSION['profile_error'] = 'Token de sécurité invalide';
            header('Location: /profile');
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $_SESSION['profile_error'] = 'Tous les champs sont requis';
            header('Location: /profile');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['profile_error'] = 'Les nouveaux mots de passe ne correspondent pas';
            header('Location: /profile');
            exit;
        }

        $user = $this->authService->getCurrentUser();
        $result = $this->authService->changePassword($user['id'], $currentPassword, $newPassword);

        if ($result['success']) {
            $_SESSION['profile_success'] = 'Mot de passe modifié avec succès';
        } else {
            $_SESSION['profile_error'] = $result['message'];
        }

        header('Location: /profile');
        exit;
    }
}
