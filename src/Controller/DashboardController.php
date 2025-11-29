<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use Statelec\Controller\SettingsController;
use PDO;

class DashboardController
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function showDashboard(): array
    {
        if (!$this->pdo) {
            return [
                'page_title' => 'Tableau de bord',
                'currentPage' => 'dashboard',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/',
                'theme' => SettingsController::getCurrentTheme()
            ];
        }

        return [
            'page_title' => 'Tableau de bord',
            'currentPage' => 'dashboard',
            'config' => $this->getSettingsConfig(),
            'theme' => SettingsController::getCurrentTheme()
        ];
    }

    private function getSettingsConfig(): array
    {
        if (!$this->pdo) {
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
        } catch (\PDOException $e) {
            error_log("Error fetching settings for Dashboard: " . $e->getMessage());
            return [];
        }
    }
}
