<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service d'authentification
 *
 * Gère l'authentification des utilisateurs, les sessions et la sécurité
 */
class AuthService
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Démarre une session sécurisée
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Régénérer l'ID de session pour éviter le session fixation
        if (!isset($_SESSION['session_started'])) {
            session_regenerate_id(true);
            $_SESSION['session_started'] = time();
        }

        // Régénérer l'ID toutes les 30 minutes
        if (isset($_SESSION['last_regeneration']) && time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        } else {
            $_SESSION['last_regeneration'] = $_SESSION['last_regeneration'] ?? time();
        }
    }

    /**
     * Authentifie un utilisateur
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        $email = strtolower(trim($email));

        // Récupérer l'utilisateur
        $user = $this->getUserByEmail($email);

        if (!$user) {
            Logger::logLogin($email, false, 'User not found');
            return ['success' => false, 'message' => 'Identifiants invalides'];
        }

        // Vérifier que le compte est actif
        if (!$user['is_active']) {
            Logger::logLogin($email, false, 'Account inactive');
            return ['success' => false, 'message' => 'Compte désactivé'];
        }

        // Vérifier le mot de passe
        if (!password_verify($password, $user['password'])) {
            Logger::logLogin($email, false, 'Invalid password');
            return ['success' => false, 'message' => 'Identifiants invalides'];
        }

        // Vérifier si le hash doit être re-généré (si l'algorithme a changé)
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->updatePassword($user['id'], $password);
        }

        // Créer la session
        $this->createUserSession($user);

        // Mettre à jour la date de dernière connexion
        $this->updateLastLogin($user['id']);

        Logger::logLogin($email, true);

        return [
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => $this->sanitizeUser($user)
        ];
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            Logger::info('User logout', ['user_id' => $_SESSION['user_id']]);
        }

        // Détruire la session
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Enregistre un nouvel utilisateur
     */
    public function register(string $email, string $password, string $name): array
    {
        $email = strtolower(trim($email));
        $name = trim($name);

        // Validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email invalide'];
        }

        if (strlen($password) < ($this->config['security']['password']['min_length'] ?? 8)) {
            return ['success' => false, 'message' => 'Mot de passe trop court (minimum 8 caractères)'];
        }

        if (strlen($name) < 2) {
            return ['success' => false, 'message' => 'Nom invalide'];
        }

        // Vérifier si l'email existe déjà
        if ($this->getUserByEmail($email)) {
            return ['success' => false, 'message' => 'Cet email est déjà utilisé'];
        }

        // Hasher le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Créer l'utilisateur
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO users (email, password, name, role, is_active, created_at)
                 VALUES (:email, :password, :name, 'user', 1, NOW())"
            );

            $stmt->execute([
                'email' => $email,
                'password' => $hashedPassword,
                'name' => $name
            ]);

            $userId = (int) $this->db->lastInsertId();

            Logger::info('User registered', [
                'user_id' => $userId,
                'email' => $email,
                'name' => $name
            ]);

            return [
                'success' => true,
                'message' => 'Inscription réussie',
                'user_id' => $userId
            ];
        } catch (\PDOException $e) {
            Logger::logDatabaseError('INSERT INTO users', $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de l\'inscription'];
        }
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    }

    /**
     * Vérifie si l'utilisateur a le rôle admin
     */
    public function isAdmin(): bool
    {
        return $this->isAuthenticated() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role']
        ];
    }

    /**
     * Change le mot de passe d'un utilisateur
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        // Récupérer l'utilisateur
        $stmt = $this->db->prepare("SELECT id, password FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur introuvable'];
        }

        // Vérifier l'ancien mot de passe
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Mot de passe actuel incorrect'];
        }

        // Vérifier la longueur du nouveau mot de passe
        if (strlen($newPassword) < ($this->config['security']['password']['min_length'] ?? 8)) {
            return ['success' => false, 'message' => 'Nouveau mot de passe trop court'];
        }

        // Mettre à jour le mot de passe
        return $this->updatePassword($userId, $newPassword);
    }

    /**
     * Réinitialise le mot de passe (admin uniquement)
     */
    public function resetPassword(int $userId, string $newPassword): array
    {
        if (strlen($newPassword) < ($this->config['security']['password']['min_length'] ?? 8)) {
            return ['success' => false, 'message' => 'Mot de passe trop court'];
        }

        return $this->updatePassword($userId, $newPassword);
    }

    /**
     * Récupère un utilisateur par email
     */
    private function getUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, email, password, name, role, is_active, email_verified
             FROM users
             WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Crée une session utilisateur
     */
    private function createUserSession(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in_at'] = time();

        // Sauvegarder la session dans la base de données (optionnel)
        $this->saveSessionToDatabase($user['id']);
    }

    /**
     * Sauvegarde la session dans la base de données
     */
    private function saveSessionToDatabase(int $userId): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
                 VALUES (:id, :user_id, :ip, :user_agent, :payload, :last_activity)
                 ON DUPLICATE KEY UPDATE
                    user_id = :user_id,
                    ip_address = :ip,
                    user_agent = :user_agent,
                    payload = :payload,
                    last_activity = :last_activity"
            );

            $stmt->execute([
                'id' => session_id(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'payload' => json_encode($_SESSION),
                'last_activity' => time()
            ]);
        } catch (\PDOException $e) {
            Logger::error('Failed to save session to database', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Met à jour la date de dernière connexion
     */
    private function updateLastLogin(int $userId): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $stmt->execute(['id' => $userId]);
        } catch (\PDOException $e) {
            Logger::error('Failed to update last login', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Met à jour le mot de passe d'un utilisateur
     */
    private function updatePassword(int $userId, string $newPassword): array
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->execute([
                'password' => $hashedPassword,
                'id' => $userId
            ]);

            Logger::info('Password updated', ['user_id' => $userId]);

            return ['success' => true, 'message' => 'Mot de passe mis à jour'];
        } catch (\PDOException $e) {
            Logger::logDatabaseError('UPDATE users password', $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
        }
    }

    /**
     * Nettoie les données utilisateur (enlève le mot de passe)
     */
    private function sanitizeUser(array $user): array
    {
        unset($user['password']);
        return $user;
    }

    /**
     * Génère un token CSRF
     */
    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie un token CSRF
     */
    public function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
