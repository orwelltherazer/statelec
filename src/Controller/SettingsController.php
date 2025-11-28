<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use PDOException;

class SettingsController
{
    private ?PDO $pdo;
    private bool $dbAvailable;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->dbAvailable = $this->pdo !== null;
    }

    /**
     * Affiche la page des paramètres
     */
    public function showSettings(): array
    {
        if (!$this->dbAvailable) {
            return [
                'page_title' => 'Paramètres',
                'currentPage' => 'parametres',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/'
            ];
        }

        $settings = $this->getAllSettings();
        return [
            'page_title' => 'Paramètres',
            'currentPage' => 'parametres',
            'settings' => $settings,
            'theme' => self::getCurrentTheme()
        ];
    }

    private function getAllSettings(): array
    {
        if (!$this->dbAvailable) {
            return [];
        }

        try {
            $stmt = $this->pdo->query("SELECT `key`, value FROM settings");
            $rawSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formattedSettings = [];
            foreach ($rawSettings as $setting) {
                $formattedSettings[$setting['key']] = json_decode($setting['value'], true);
            }
            return $formattedSettings;
        } catch (PDOException $e) {
            // Log error, return empty array or handle gracefully
            return [];
        }
    }

    /**
     * Get current theme from settings
     */
    public static function getCurrentTheme(): string
    {
        try {
            $pdo = Database::getInstance();
            if (!$pdo) {
                return 'HeadLight'; // Default theme
            }

            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute(['app_theme']);
            $result = $stmt->fetchColumn();

            if ($result !== false) {
                $theme = json_decode($result, true);
                return $theme ?: 'HeadLight';
            }

            return 'HeadLight'; // Default theme
        } catch (PDOException $e) {
            return 'HeadLight'; // Default theme on error
        }
    }

    /**
     * Gère GET /api/settings/{key}
     */
    public function getSetting(string $key): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn(); // Fetch only the value

            header('Content-Type: application/json');
            echo json_encode($result !== false ? json_decode($result, true) : null); // Decode JSON stored in DB

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur de base de données: " . $e->getMessage()]);
        }
    }

    /**
     * Gère POST /api/settings/{key}
     */
    public function saveSetting(string $key): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($input['value'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON or missing value']);
                return;
            }

            $value = json_encode($input['value']); // Encode value to JSON for storage

            $stmt = $this->pdo->prepare("
                INSERT INTO settings (`key`, value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([$key, $value]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur de base de données: " . $e->getMessage()]);
        }
    }
}
