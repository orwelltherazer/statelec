<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Service de gestion des utilisateurs
 *
 * CRUD complet pour les utilisateurs
 */
class UserService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère tous les utilisateurs
     */
    public function getAllUsers(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, email, name, role, is_active, email_verified, last_login, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Compte le nombre total d'utilisateurs
     */
    public function countUsers(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Récupère un utilisateur par ID
     */
    public function getUserById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, email, name, role, is_active, email_verified, last_login, created_at, updated_at
             FROM users
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Crée un nouvel utilisateur
     */
    public function createUser(string $email, string $name, string $password, string $role = 'user'): array
    {
        $email = strtolower(trim($email));
        $name = trim($name);

        // Validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email invalide'];
        }

        if (!in_array($role, ['user', 'admin'])) {
            return ['success' => false, 'message' => 'Rôle invalide'];
        }

        // Vérifier si l'email existe déjà
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Cet email est déjà utilisé'];
        }

        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare(
                "INSERT INTO users (email, password, name, role, is_active, created_at)
                 VALUES (:email, :password, :name, :role, 1, NOW())"
            );

            $stmt->execute([
                'email' => $email,
                'password' => $hashedPassword,
                'name' => $name,
                'role' => $role
            ]);

            $userId = (int) $this->db->lastInsertId();

            Logger::info('User created', [
                'user_id' => $userId,
                'email' => $email,
                'role' => $role
            ]);

            return [
                'success' => true,
                'message' => 'Utilisateur créé',
                'user_id' => $userId
            ];
        } catch (\PDOException $e) {
            Logger::logDatabaseError('INSERT INTO users', $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la création'];
        }
    }

    /**
     * Met à jour un utilisateur
     */
    public function updateUser(int $id, array $data): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur introuvable'];
        }

        $updates = [];
        $params = ['id' => $id];

        // Nom
        if (isset($data['name'])) {
            $updates[] = "name = :name";
            $params['name'] = trim($data['name']);
        }

        // Email
        if (isset($data['email'])) {
            $email = strtolower(trim($data['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email invalide'];
            }
            if ($email !== $user['email'] && $this->emailExists($email)) {
                return ['success' => false, 'message' => 'Cet email est déjà utilisé'];
            }
            $updates[] = "email = :email";
            $params['email'] = $email;
        }

        // Rôle
        if (isset($data['role'])) {
            if (!in_array($data['role'], ['user', 'admin'])) {
                return ['success' => false, 'message' => 'Rôle invalide'];
            }
            $updates[] = "role = :role";
            $params['role'] = $data['role'];
        }

        // Statut actif
        if (isset($data['is_active'])) {
            $updates[] = "is_active = :is_active";
            $params['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'Aucune modification'];
        }

        try {
            $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            Logger::info('User updated', ['user_id' => $id, 'changes' => array_keys($params)]);

            return ['success' => true, 'message' => 'Utilisateur mis à jour'];
        } catch (\PDOException $e) {
            Logger::logDatabaseError('UPDATE users', $e->getMessage(), $params);
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
        }
    }

    /**
     * Supprime un utilisateur
     */
    public function deleteUser(int $id): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur introuvable'];
        }

        // Empêcher la suppression du dernier admin
        if ($user['role'] === 'admin') {
            $adminCount = $this->countUsersByRole('admin');
            if ($adminCount <= 1) {
                return ['success' => false, 'message' => 'Impossible de supprimer le dernier administrateur'];
            }
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);

            Logger::warning('User deleted', [
                'user_id' => $id,
                'email' => $user['email']
            ]);

            return ['success' => true, 'message' => 'Utilisateur supprimé'];
        } catch (\PDOException $e) {
            Logger::logDatabaseError('DELETE FROM users', $e->getMessage(), ['id' => $id]);
            return ['success' => false, 'message' => 'Erreur lors de la suppression'];
        }
    }

    /**
     * Active/désactive un utilisateur
     */
    public function toggleUserStatus(int $id): array
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur introuvable'];
        }

        $newStatus = !$user['is_active'];

        try {
            $stmt = $this->db->prepare("UPDATE users SET is_active = :status WHERE id = :id");
            $stmt->execute(['status' => $newStatus ? 1 : 0, 'id' => $id]);

            Logger::info('User status toggled', [
                'user_id' => $id,
                'new_status' => $newStatus ? 'active' : 'inactive'
            ]);

            return [
                'success' => true,
                'message' => $newStatus ? 'Utilisateur activé' : 'Utilisateur désactivé',
                'is_active' => $newStatus
            ];
        } catch (\PDOException $e) {
            Logger::logDatabaseError('UPDATE users status', $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la modification'];
        }
    }

    /**
     * Récupère les préférences d'un utilisateur
     */
    public function getUserPreferences(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT preference_key, preference_value
             FROM user_preferences
             WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);

        $preferences = [];
        while ($row = $stmt->fetch()) {
            $preferences[$row['preference_key']] = json_decode($row['preference_value'], true);
        }

        return $preferences;
    }

    /**
     * Met à jour une préférence utilisateur
     */
    public function setUserPreference(int $userId, string $key, $value): bool
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO user_preferences (user_id, preference_key, preference_value, updated_at)
                 VALUES (:user_id, :key, :value, NOW())
                 ON DUPLICATE KEY UPDATE preference_value = :value, updated_at = NOW()"
            );

            $stmt->execute([
                'user_id' => $userId,
                'key' => $key,
                'value' => json_encode($value)
            ]);

            return true;
        } catch (\PDOException $e) {
            Logger::logDatabaseError('UPDATE user_preferences', $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un email existe
     */
    private function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Compte les utilisateurs par rôle
     */
    private function countUsersByRole(string $role): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
        $stmt->execute(['role' => $role]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Recherche des utilisateurs
     */
    public function searchUsers(string $query, int $limit = 50): array
    {
        $searchTerm = "%{$query}%";

        $stmt = $this->db->prepare(
            "SELECT id, email, name, role, is_active, last_login, created_at
             FROM users
             WHERE email LIKE :search OR name LIKE :search
             ORDER BY name ASC
             LIMIT :limit"
        );

        $stmt->bindValue('search', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Récupère les statistiques des utilisateurs
     */
    public function getUserStats(): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'admins' => 0,
            'users' => 0,
            'recent_logins' => 0
        ];

        // Total
        $stats['total'] = $this->countUsers();

        // Par statut
        $stmt = $this->db->query(
            "SELECT is_active, COUNT(*) as count FROM users GROUP BY is_active"
        );
        while ($row = $stmt->fetch()) {
            if ($row['is_active']) {
                $stats['active'] = (int) $row['count'];
            } else {
                $stats['inactive'] = (int) $row['count'];
            }
        }

        // Par rôle
        $stmt = $this->db->query(
            "SELECT role, COUNT(*) as count FROM users GROUP BY role"
        );
        while ($row = $stmt->fetch()) {
            if ($row['role'] === 'admin') {
                $stats['admins'] = (int) $row['count'];
            } else {
                $stats['users'] = (int) $row['count'];
            }
        }

        // Connexions récentes (dernières 24h)
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stats['recent_logins'] = (int) $stmt->fetchColumn();

        return $stats;
    }
}
