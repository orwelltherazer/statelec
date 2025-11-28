<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use DateTime;
use DateTimeZone;

class HistoriqueController
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function showHistorique(): array
    {
        if (!$this->pdo) {
            return [
                'page_title' => 'Historique',
                'currentPage' => 'historique',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/',
                'theme' => \Statelec\Controller\SettingsController::getCurrentTheme()
            ];
        }

        $globalDataRange = $this->getGlobalDataRange();
        $totalRecordCount = $this->getTotalRecordCount();
        $globalAveragePower = $this->getGlobalAveragePower();
        return [
            'page_title' => 'Historique',
            'currentPage' => 'historique',
            'initialChartData' => [], // Will be populated by JS via API
            'initialHistoricalData' => [], // Will be populated by JS via API
            'config' => $this->getSettingsConfig(), // Pass settings for timezone etc.
            'globalDataRange' => $globalDataRange,
            'totalRecordCount' => $totalRecordCount,
            'globalAveragePower' => $globalAveragePower,
            'theme' => \Statelec\Controller\SettingsController::getCurrentTheme()
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
        } catch (PDOException $e) {
            error_log("Error fetching settings for Historique: " . $e->getMessage());
            return [];
        }
    }

    private function getGlobalDataRange(): array
    {
        if (!$this->pdo) {
            return ['min' => null, 'max' => null];
        }

        try {
            $stmt = $this->pdo->query("SELECT MIN(timestamp) as min_timestamp, MAX(timestamp) as max_timestamp FROM consumption_data");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $minTimestamp = $result['min_timestamp'] ? (new DateTime($result['min_timestamp']))->format('Y-m-d H:i:s') : null;
            $maxTimestamp = $result['max_timestamp'] ? (new DateTime($result['max_timestamp']))->format('Y-m-d H:i:s') : null;

            return [
                'min' => $minTimestamp,
                'max' => $maxTimestamp
            ];
        } catch (PDOException $e) {
            error_log("Error fetching global data range for Historique: " . $e->getMessage());
            return ['min' => null, 'max' => null];
        }
    }

    private function getTotalRecordCount(): int
    {
        if (!$this->pdo) {
            return 0;
        }

        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM consumption_data");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error fetching total record count for Historique: " . $e->getMessage());
            return 0;
        }
    }

    private function getGlobalAveragePower(): int
    {
        if (!$this->pdo) {
            return 0;
        }

        try {
            $stmt = $this->pdo->query("SELECT AVG(papp) FROM consumption_data");
            return (int)round((float)$stmt->fetchColumn());
        } catch (PDOException $e) {
            error_log("Error fetching global average power for Historique: " . $e->getMessage());
            return 0;
        }
    }
}
