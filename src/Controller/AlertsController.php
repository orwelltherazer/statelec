<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use DateTime;

class AlertsController
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function showAlerts(): array
    {
        if (!$this->pdo) {
            return [
                'page_title' => 'Alertes',
                'currentPage' => 'alertes',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/',
                'theme' => 'light'
            ];
        }

        $alerts = $this->getAlertsData();

        // Récupérer les settings pour afficher dans le formulaire
        $settingsStmt = $this->pdo->query("SELECT `key`, value FROM settings WHERE `key` IN ('seuilPuissance', 'seuilJournalier')");
        $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings[$key] = json_decode($value, true);
        }

        return [
            'page_title' => 'Alertes',
            'currentPage' => 'alertes',
            'alerts' => $alerts,
            'settings' => $settings,
            'theme' => 'light' // TODO: Implement theme switching
        ];
    }

    private function getAlertsData(): array
    {
        if (!$this->pdo) {
            return [
                ['id' => 1, 'severity' => 'error', 'message' => 'Connexion à la base de données impossible', 'timestamp' => (new DateTime())->format('Y-m-d H:i:s')]
            ];
        }

        try {
            // Vérifier si la table alerts_log existe
            $tableExists = $this->pdo->query("SHOW TABLES LIKE 'alerts_log'")->rowCount() > 0;
            
            if (!$tableExists) {
                return [
                    ['id' => 1, 'severity' => 'info', 'message' => 'Table des alertes non initialisée. Exécutez le script SQL database/alerts_log.sql', 'timestamp' => (new DateTime())->format('Y-m-d H:i:s')]
                ];
            }
            
            // Récupérer les alertes récentes (derniers 30 jours)
            $stmt = $this->pdo->query("
                SELECT id, severity, message, created_at as timestamp 
                FROM alerts_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($alerts)) {
                return [
                    ['id' => 1, 'severity' => 'info', 'message' => 'Aucune alerte enregistrée dans les 30 derniers jours', 'timestamp' => (new DateTime())->format('Y-m-d H:i:s')]
                ];
            }
            
            return $alerts;
            
        } catch (PDOException $e) {
            return [
                ['id' => 1, 'severity' => 'info', 'message' => 'Impossible de charger les alertes: ' . $e->getMessage(), 'timestamp' => (new DateTime())->format('Y-m-d H:i:s')]
            ];
        }
    }
}