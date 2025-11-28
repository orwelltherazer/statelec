<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use DateTime;
use DateTimeZone;

class CostController
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function showCost(): array
    {
        if (!$this->pdo) {
            return [
                'page_title' => 'Coûts',
                'currentPage' => 'cout',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/',
                'theme' => \Statelec\Controller\SettingsController::getCurrentTheme()
            ];
        }

        $data = $this->getCostData();

        // Fetch configuration settings
        $settingsStmt = $this->pdo->query("SELECT `key`, value FROM settings WHERE `key` IN ('prixHC', 'prixHP', 'prix_hc', 'prix_hp', 'budgetMensuel', 'subscription_type', 'subscription_price', 'prixBase')");
        $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Decode JSON values
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings[$key] = json_decode($value, true);
        }

        $config = [
            'prixHC' => isset($settings['prixHC']) ? (float)$settings['prixHC'] : (isset($settings['prix_hc']) ? (float)$settings['prix_hc'] : 0.1821),
            'prixHP' => isset($settings['prixHP']) ? (float)$settings['prixHP'] : (isset($settings['prix_hp']) ? (float)$settings['prix_hp'] : 0.2460),
            'prixBase' => isset($settings['prixBase']) ? (float)$settings['prixBase'] : 0.2000,
            'budgetMensuel' => isset($settings['budgetMensuel']) ? (float)$settings['budgetMensuel'] : 50.0,
            'subscription_type' => isset($settings['subscription_type']) ? $settings['subscription_type'] : 'hchp',
            'subscription_price' => isset($settings['subscription_price']) ? (float)$settings['subscription_price'] : 0.0
        ];

        return [
            'page_title' => 'Coûts',
            'currentPage' => 'cout',
            'costData' => $data['costData'],
            'totalCost' => $data['totalCost'],
            'averageDailyCost' => $data['averageDailyCost'],
            'config' => $config,
            'theme' => \Statelec\Controller\SettingsController::getCurrentTheme()
        ];
    }

    private function getCostData(): array
    {
        if (!$this->pdo) {
            return $this->getDefaultCostData();
        }

        // Fetch all consumption data
        $stmt = $this->pdo->query("SELECT timestamp, hchc, hchp FROM consumption_data ORDER BY timestamp ASC");
        $consumptionData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($consumptionData)) {
            return $this->getDefaultCostData();
        }

        // Fetch prices from settings (or use defaults)
        $settingsStmt = $this->pdo->query("SELECT `key`, value FROM settings WHERE `key` IN ('prixHC', 'prixHP', 'prix_hc', 'prix_hp', 'subscription_type', 'subscription_price', 'prixBase')");
        $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings[$key] = json_decode($value, true);
        }

        $prixHC = isset($settings['prixHC']) ? (float)$settings['prixHC'] : (isset($settings['prix_hc']) ? (float)$settings['prix_hc'] : 0.1821);
        $prixHP = isset($settings['prixHP']) ? (float)$settings['prixHP'] : (isset($settings['prix_hp']) ? (float)$settings['prix_hp'] : 0.2460);
        $prixBase = isset($settings['prixBase']) ? (float)$settings['prixBase'] : 0.2000;
        
        $subscriptionType = isset($settings['subscription_type']) ? $settings['subscription_type'] : 'hchp';
        $subscriptionPrice = isset($settings['subscription_price']) ? (float)$settings['subscription_price'] : 0.0;
        
        // Calculate daily subscription cost (monthly * 12 / 365)
        $dailySubscriptionCost = ($subscriptionPrice * 12) / 365;

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

        $costData = [];
        $totalCost = 0.0;
        foreach ($dailyConsumption as $date => $data) {
            $consoHC = $data['hchc_end'] - $data['hchc_start'];
            $consoHP = $data['hchp_end'] - $data['hchp_start'];
            $dailyTotalConso = $consoHC + $consoHP;
            
            if ($subscriptionType === 'base') {
                $dailyConsumptionCost = $dailyTotalConso * $prixBase;
            } else {
                $dailyConsumptionCost = ($consoHC * $prixHC) + ($consoHP * $prixHP);
            }
            
            $dailyCost = $dailyConsumptionCost + $dailySubscriptionCost;
            $totalCost += $dailyCost;

            $costData[] = [
                'date' => $date,
                'consoHC' => round($consoHC, 2),
                'consoHP' => round($consoHP, 2),
                'totalConso' => round($dailyTotalConso, 2),
                'cost' => round($dailyCost, 2)
            ];
        }

        $numDays = count($dailyConsumption);
        $averageDailyCost = $numDays > 0 ? $totalCost / $numDays : 0.0;

        return [
            'costData' => $costData,
            'totalCost' => number_format($totalCost, 2, ',', ''),
            'averageDailyCost' => number_format($averageDailyCost, 2, ',', '')
        ];
    }

    private function getDefaultCostData(): array
    {
        return [
            'costData' => [],
            'totalCost' => '0,00',
            'averageDailyCost' => '0,00'
        ];
    }
}
