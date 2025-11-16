<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use DateTime;
use DateTimeZone;

class AnalysisController
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function showAnalysis(): array
    {
        if (!$this->pdo) {
            return [
                'page_title' => 'Analyse',
                'currentPage' => 'analyse',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/',
                'theme' => 'light'
            ];
        }

        $data = $this->getAnalysisData();

        // Fetch configuration settings
        $settingsStmt = $this->pdo->query("SELECT `key`, value FROM settings WHERE `key` IN ('prixHC', 'prixHP', 'budgetMensuel')");
        $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Decode JSON values
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings[$key] = json_decode($value, true);
        }

        $config = [
            'prixHC' => isset($settings['prixHC']) ? (float)$settings['prixHC'] : 0.1821,
            'prixHP' => isset($settings['prixHP']) ? (float)$settings['prixHP'] : 0.2460,
            'budgetMensuel' => isset($settings['budgetMensuel']) ? (float)$settings['budgetMensuel'] : 50.0
        ];

        return [
            'page_title' => 'Analyse',
            'currentPage' => 'analyse',
            'analysisData' => $data['analysisData'],
            'config' => $config
        ];
    }

    private function getAnalysisData(): array
    {
        if (!$this->pdo) {
            return $this->getDefaultAnalysisData();
        }

        // Fetch consumption data for the last 30 days
        $now = new DateTime('now', new DateTimeZone($_ENV['TIMEZONE'] ?? 'Europe/Paris'));
        $thirtyDaysAgo = (clone $now)->modify('-30 days');

        $stmt = $this->pdo->prepare("SELECT timestamp, hchc, hchp FROM consumption_data WHERE timestamp >= ? ORDER BY timestamp ASC");
        $stmt->execute([$thirtyDaysAgo->format('Y-m-d H:i:s')]);
        $consumptionData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($consumptionData)) {
            return $this->getDefaultAnalysisData();
        }

        $dailyConsumption = [];
        foreach ($consumptionData as $record) {
            $date = (new DateTime($record['timestamp']))->format('Y-m-d');
            if (!isset($dailyConsumption[$date])) {
                $dailyConsumption[$date] = ['hchc_start' => (float)$record['hchc'], 'hchp_start' => (float)$record['hchp'], 'hchc_end' => (float)$record['hchc'], 'hchp_end' => (float)$record['hchp']];
            } else {
                $dailyConsumption[$date]['hchc_end'] = (float)$record['hchc'];
                $dailyConsumption[$date]['hchp_end'] = (float)$record['hchp'];
            }
        }

        $analysisData = [];
        foreach ($dailyConsumption as $date => $data) {
            $consoHC = $data['hchc_end'] - $data['hchc_start'];
            $consoHP = $data['hchp_end'] - $data['hchp_start'];
            $totalConso = $consoHC + $consoHP;

            $analysisData[] = [
                'date' => $date,
                'consoHC' => round($consoHC, 2),
                'consoHP' => round($consoHP, 2),
                'totalConso' => round($totalConso, 2)
            ];
        }

        return [
            'analysisData' => $analysisData
        ];
    }

    private function getDefaultAnalysisData(): array
    {
        return [
            'analysisData' => []
        ];
    }
}
