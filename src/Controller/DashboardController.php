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
            'theme' => 'light' // TODO: Implement theme switching
        ], $data);
    }

    private function getDashboardData(): array
    {
        if (!$this->pdo) {
            return $this->getDefaultDashboardData();
        }

        try {
            // Fetch consumption data for the last 24 hours
            $stmt = $this->pdo->query("SELECT timestamp, papp, hchc, hchp, ptec FROM consumption_data WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY timestamp ASC");
            $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($historicalData)) {
                return $this->getDefaultDashboardData();
            }

            // Get current data (latest record)
            $currentData = end($historicalData);

            // Initialize time variables
            $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
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
            
            // Default prices from original React app
            $prixHC = 0.1821;
            $prixHP = 0.2460;
            $coutEstime = round($conso24hHP * $prixHP + $conso24hHC * $prixHC, 2);

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
            }

            $chartData = [];
            if (count($sampledData) > 0) {
                $firstChartData = $sampledData[0];
                $firstHC = (float)$firstChartData['hchc'];
                $firstHP = (float)$firstChartData['hchp'];

                foreach ($sampledData as $d) {
                    $hcConsumption = round((float)$d['hchc'] - $firstHC, 2);
                    $hpConsumption = round((float)$d['hchp'] - $firstHP, 2);

                    $chartData[] = [
                        'time' => (new DateTime($d['timestamp']))->format('H:i'),
                        'indexHc' => $hcConsumption,
                        'indexHp' => $hpConsumption
                    ];
                }
            }

            return [
                'consoDuJour' => number_format($consoDuJour, 2, ',', ''),
                'coutEstime' => number_format($coutEstime, 2, ',', ''),
                'puissanceMax' => $puissanceMax,
                'variationText' => $variationText,
                'variationColor' => $variationColor,
                'conso24hHC' => number_format($conso24hHC, 2, ',', ''),
                'conso24hHP' => number_format($conso24hHP, 2, ',', ''),
                'currentData' => [
                    'hchc' => number_format((float)$currentData['hchc'], 1, ',', ''),
                    'hchp' => number_format((float)$currentData['hchp'], 1, ',', '')
                ],
                'chartData' => $chartData,
                'theme' => 'light' // Default theme, will be dynamic later
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
            'chartData' => [],
            'theme' => 'light'
        ];
    }
}
