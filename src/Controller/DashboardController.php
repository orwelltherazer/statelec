<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use DateTime;
use DateTimeZone;

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
                'page_title' => 'Dashboard',
                'currentPage' => 'dashboard',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/',
                'theme' => 'light'
            ];
        }

        $data = $this->getDashboardData();
        return array_merge([
            'page_title' => 'Dashboard',
            'currentPage' => 'dashboard',
            'theme' => \Statelec\Controller\SettingsController::getCurrentTheme()
        ], $data);
    }

    private function getDashboardData(): array
    {
        if (!$this->pdo) {
            return $this->getDefaultDashboardData();
        }

        try {
            // Fetch consumption data for the last 48 hours to allow comparison
            $stmt = $this->pdo->query("SELECT timestamp, papp, hchc, hchp, ptec FROM consumption_data WHERE timestamp >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 48 HOUR) ORDER BY timestamp ASC");
            $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($historicalData)) {
                return $this->getDefaultDashboardData();
            }

            // Get current data (latest record)
            $currentData = end($historicalData);

            // Initialize time variables
            $now = new DateTime('now', new DateTimeZone($_ENV['TIMEZONE'] ?? 'Europe/Paris'));
            $twentyFourHoursAgo = (clone $now)->modify('-24 hours');
            $fortyEightHoursAgo = (clone $now)->modify('-48 hours');

            // Filter data for the last 24 hours
            $last24h = array_filter($historicalData, function ($d) use ($twentyFourHoursAgo) {
                return new DateTime($d['timestamp']) >= $twentyFourHoursAgo;
            });
            $last24h = array_values($last24h); // Re-index array

            // Filter data for yesterday (24-48h before)
            $yesterday24h = array_filter($historicalData, function ($d) use ($twentyFourHoursAgo, $fortyEightHoursAgo) {
                $timestamp = new DateTime($d['timestamp']);
                return $timestamp >= $fortyEightHoursAgo && $timestamp < $twentyFourHoursAgo;
            });
            $yesterday24h = array_values($yesterday24h); // Re-index array

            // Calculate consumption for last 24h
            $conso24hHC = 0.0;
            $conso24hHP = 0.0;
            if (count($last24h) > 0) {
                $firstData = $last24h[0];
                $lastData = end($last24h);
                $conso24hHC = round((float)$lastData['hchc'] - (float)$firstData['hchc'], 2);
                $conso24hHP = round((float)$lastData['hchp'] - (float)$firstData['hchp'], 2);
            }

            // Calculate consumption for yesterday
            $consoHierHC = 0.0;
            $consoHierHP = 0.0;
            if (count($yesterday24h) > 0) {
                $firstYesterdayData = $yesterday24h[0];
                $lastYesterdayData = end($yesterday24h);
                $consoHierHC = round((float)$lastYesterdayData['hchc'] - (float)$firstYesterdayData['hchc'], 2);
                $consoHierHP = round((float)$lastYesterdayData['hchp'] - (float)$firstYesterdayData['hchp'], 2);
            }

            $consoDuJour = round($conso24hHC + $conso24hHP, 2);
            $consoHier = round($consoHierHC + $consoHierHP, 2);
            
            // Fetch prices from settings
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
            $dailySubscriptionCost = ($subscriptionPrice * 12) / 365;

            if ($subscriptionType === 'base') {
                $coutBase = ($conso24hHP + $conso24hHC) * $prixBase;
                $coutHC = 0;
                $coutHP = 0;
                $coutEstime = $coutBase;
            } else {
                $coutHC = $conso24hHC * $prixHC;
                $coutHP = $conso24hHP * $prixHP;
                $coutBase = 0;
                $coutEstime = $coutHC + $coutHP;
            }
            
            $coutAbo = $dailySubscriptionCost;
            $coutEstime += $coutAbo;
            
            $coutEstime = round($coutEstime, 2);
            $coutHC = round($coutHC, 2);
            $coutHP = round($coutHP, 2);
            $coutBase = round($coutBase, 2);
            $coutAbo = round($coutAbo, 2);

            // Calculate variation vs yesterday
            $variationText = 'Pas de données hier';
            $variationColor = 'text-gray-500';
            if ($consoHier > 0) {
                $variation = (($consoDuJour - $consoHier) / $consoHier) * 100;
                $variationVsHier = round($variation, 1);

                if ($variation > 0) {
                    $variationText = "+{$variationVsHier}% vs hier";
                    $variationColor = 'text-red-500';
                } elseif ($variation < 0) {
                    $variationText = "{$variationVsHier}% vs hier";
                    $variationColor = 'text-green-500';
                } else {
                    $variationText = '0% vs hier';
                    $variationColor = 'text-gray-500';
                }
            }

            // Calculate max power
            $puissanceMax = 0;
            if (count($last24h) > 0) {
                $puissanceMax = max(array_map(function ($d) { return (int)$d['papp']; }, $last24h));
            }

            // Prepare chart data (sampled every 15 minutes)
            $sampledData = [];
            if (count($last24h) > 0) {
                $lastSampleTime = null;
                foreach ($last24h as $dataPoint) {
                    $dataTime = new DateTime($dataPoint['timestamp']);
                    if ($lastSampleTime === null || ($dataTime->getTimestamp() - $lastSampleTime->getTimestamp()) >= 15 * 60) {
                        $sampledData[] = $dataPoint;
                        $lastSampleTime = $dataTime;
                    }
                }

                // Ensure the very last data point is included for chart completeness
                $lastDataPoint = end($last24h);
                if (!empty($lastDataPoint)) {
                    $isLastPointInSampled = false;
                    if (!empty($sampledData)) {
                        $lastSampledPoint = end($sampledData);
                        if ($lastSampledPoint['timestamp'] === $lastDataPoint['timestamp']) {
                            $isLastPointInSampled = true;
                        }
                    }

                    if (!$isLastPointInSampled) {
                        $sampledData[] = $lastDataPoint;
                    }
                }
            }

            $chartData = [];
            if (count($sampledData) > 0) {
                $firstChartData = $sampledData[0];
                $firstHC = (float)$firstChartData['hchc'];
                $firstHP = (float)$firstChartData['hchp'];
                $appTimezone = new DateTimeZone($_ENV['TIMEZONE'] ?? 'Europe/Paris');

                foreach ($sampledData as $d) {
                    $hcConsumption = round((float)$d['hchc'] - $firstHC, 2);
                    $hpConsumption = round((float)$d['hchp'] - $firstHP, 2);

                    $timestamp = new DateTime($d['timestamp']); // Automatically parsed as UTC
                    $timestamp->setTimezone($appTimezone); // Convert to local timezone

                    $chartData[] = [
                        'time' => $timestamp->format('H:i'),
                        'indexHc' => $hcConsumption,
                        'indexHp' => $hpConsumption
                    ];
                }
            }

            return [
                'consoDuJour' => number_format($consoDuJour, 2, ',', ''),
                'coutEstime' => number_format($coutEstime, 2, ',', ''),
                'coutHC' => number_format($coutHC, 2, ',', ''),
                'coutHP' => number_format($coutHP, 2, ',', ''),
                'coutBase' => number_format($coutBase, 2, ',', ''),
                'coutAbo' => number_format($coutAbo, 2, ',', ''),
                'puissanceMax' => $puissanceMax,
                'variationText' => $variationText,
                'variationColor' => $variationColor,
                'conso24hHC' => number_format($conso24hHC, 2, ',', ''),
                'conso24hHP' => number_format($conso24hHP, 2, ',', ''),
                'currentData' => [
                    'hchc' => number_format((float)$currentData['hchc'], 1, ',', ''),
                    'hchp' => number_format((float)$currentData['hchp'], 1, ',', '')
                ],
                'chartData' => $chartData
            ];

        } catch (Exception $e) {
            error_log('Dashboard data error: ' . $e->getMessage());
            return $this->getDefaultDashboardData();
        }
    }

    private function getDefaultDashboardData(): array
    {
        return [
            'consoDuJour' => '0,00',
            'coutEstime' => '0,00',
            'puissanceMax' => 0,
            'variationText' => 'Pas de données',
            'variationColor' => 'text-gray-500',
            'conso24hHC' => '0,00',
            'conso24hHP' => '0,00',
            'currentData' => ['hchc' => '0,0', 'hchp' => '0,0'],
            'chartData' => []
        ];
    }
}
