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

        $config = $this->getSettingsConfig();
        $data24h = $this->getLast24hData();
        
        // Calculs par défaut
        $consoDuJour = 0; // Wh
        $coutEstime = 0;
        $puissanceMax = 0;
        $conso24hHC = 0; // Wh
        $conso24hHP = 0; // Wh
        $chartData = [];
        $currentData = ['hchc' => 0, 'hchp' => 0];
        
        $prixHC = (float)($config['prixHC'] ?? 0.1821);
        $prixHP = (float)($config['prixHP'] ?? 0.2460);
        $prixBase = (float)($config['prixBase'] ?? 0.2000);
        $subType = $config['subscription_type'] ?? 'hchp';
        $aboMensuel = (float)($config['subscription_price'] ?? 0);
        $aboJour = ($aboMensuel * 12) / 365;
        
        $coutBaseVal = 0;
        $coutHCVal = 0;
        $coutHPVal = 0;

        if (!empty($data24h)) {
            $first = $data24h[0];
            $last = end($data24h);
            $currentData = $last;
            
            // Consommation sur la période (différence d'index)
            $conso24hHC = max(0, (float)$last['hchc'] - (float)$first['hchc']);
            $conso24hHP = max(0, (float)$last['hchp'] - (float)$first['hchp']);
            $consoDuJour = $conso24hHC + $conso24hHP;
            
            // Calcul coût (conversion Wh -> kWh)
            if ($subType === 'base') {
                $coutBaseVal = ($consoDuJour / 1000) * $prixBase;
                $coutEstime = $coutBaseVal + $aboJour;
            } else {
                $coutHCVal = ($conso24hHC / 1000) * $prixHC;
                $coutHPVal = ($conso24hHP / 1000) * $prixHP;
                $coutEstime = $coutHCVal + $coutHPVal + $aboJour;
            }
            
            // Puissance max et données graphique
            $puissanceMax = 0;
            
            // Échantillonnage pour le graphique (max 100 points)
            $totalPoints = count($data24h);
            $step = max(1, floor($totalPoints / 100));
            
            foreach ($data24h as $i => $row) {
                if ((int)$row['papp'] > $puissanceMax) {
                    $puissanceMax = (int)$row['papp'];
                }
                
                if ($i % $step === 0 || $i === $totalPoints - 1) {
                    $chartData[] = [
                        'time' => (new \DateTime($row['timestamp']))->format('H:i'),
                        'indexHc' => number_format(((float)$row['hchc'] - (float)$first['hchc']) / 1000, 3, '.', ''), // Delta en kWh
                        'indexHp' => number_format(((float)$row['hchp'] - (float)$first['hchp']) / 1000, 3, '.', '')  // Delta en kWh
                    ];
                }
            }
        }
        
        // Variation (logique simplifiée pour l'instant)
        $variationText = "";
        $variationColor = "text-gray-500";

        return [
            'page_title' => 'Tableau de bord',
            'currentPage' => 'dashboard',
            'config' => $config,
            'theme' => SettingsController::getCurrentTheme(),
            
            'consoDuJour' => number_format($consoDuJour / 1000, 2, ',', ' '), // kWh
            'coutEstime' => number_format($coutEstime, 2, ',', ' '),
            'puissanceMax' => $puissanceMax,
            
            'currentData' => [
                'hchc' => number_format(((float)($currentData['hchc'] ?? 0)) / 1000, 1, ',', ' '),
                'hchp' => number_format(((float)($currentData['hchp'] ?? 0)) / 1000, 1, ',', ' ')
            ],
            
            'conso24hHC' => number_format($conso24hHC / 1000, 2, ',', ' '),
            'conso24hHP' => number_format($conso24hHP / 1000, 2, ',', ' '),
            
            'chartData' => $chartData,
            
            'variationText' => $variationText,
            'variationColor' => $variationColor,
            
            'coutBase' => number_format($coutBaseVal, 2, ',', ' '),
            'coutHC' => number_format($coutHCVal, 2, ',', ' '),
            'coutHP' => number_format($coutHPVal, 2, ',', ' '),
            'coutAbo' => number_format($aboJour, 2, ',', ' ')
        ];
    }

    private function getLast24hData(): array
    {
        if (!$this->pdo) return [];
        try {
            // Get data from last 24h
            $stmt = $this->pdo->query("
                SELECT * FROM consumption_data 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                ORDER BY timestamp ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
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
