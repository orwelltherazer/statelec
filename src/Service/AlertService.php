<?php

declare(strict_types=1);

namespace Statelec\Service;

use Statelec\Service\Database;
use PDO;
use DateTime;

/**
 * Service d'alerte automatique avec envoi d'emails
 */
class AlertService
{
    private PDO $pdo;
    private array $config;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->config = $this->loadConfig();
    }
    
    /**
     * Point d'entrée principal pour le cron
     */
    public function checkAndSendAlerts(): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] Début de la vérification des alertes\n";
        
        // Récupérer les seuils configurés
        $thresholds = $this->getThresholds();
        
        if (empty($thresholds)) {
            echo "[" . date('Y-m-d H:i:s') . "] Aucun seuil configuré\n";
            return;
        }
        
        $alertsSent = 0;
        
        // Vérifier les dépassements de puissance
        if (isset($thresholds['seuilPuissance']) && $thresholds['seuilPuissance'] > 0) {
            $powerAlerts = $this->checkPowerThreshold($thresholds['seuilPuissance']);
            foreach ($powerAlerts as $alert) {
                if ($this->sendAlertEmail($alert)) {
                    $this->logAlert($alert);
                    $alertsSent++;
                }
            }
        }
        
        // Vérifier la consommation journalière
        if (isset($thresholds['seuilJournalier']) && $thresholds['seuilJournalier'] > 0) {
            $dailyAlert = $this->checkDailyConsumption($thresholds['seuilJournalier']);
            if ($dailyAlert && $this->sendAlertEmail($dailyAlert)) {
                $this->logAlert($dailyAlert);
                $alertsSent++;
            }
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] $alertsSent alerte(s) envoyée(s)\n";
    }
    
    /**
     * Charge la configuration depuis les settings
     */
    private function loadConfig(): array
    {
        $settingsStmt = $this->pdo->query("SELECT `key`, value FROM settings WHERE `key` IN ('email_alerts', 'email_destinataire')");
        $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings[$key] = json_decode($value, true);
        }
        
        return [
            'enabled' => $settings['email_alerts'] ?? false,
            'email' => $settings['email_destinataire'] ?? null
        ];
    }
    
    /**
     * Récupère les seuils configurés
     */
    private function getThresholds(): array
    {
        $settingsStmt = $this->pdo->query("SELECT `key`, value FROM settings WHERE `key` IN ('seuilPuissance', 'seuilJournalier')");
        $rawSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $thresholds = [];
        foreach ($rawSettings as $key => $value) {
            $thresholds[$key] = json_decode($value, true);
        }
        
        return $thresholds;
    }
    
    /**
     * Vérifie les dépassements de puissance (dernière heure)
     */
    private function checkPowerThreshold(int $seuilPuissance): array
    {
        $alerts = [];
        $oneHourAgo = new DateTime();
        $oneHourAgo->modify('-1 hour');
        
        $stmt = $this->pdo->prepare("
            SELECT timestamp, papp 
            FROM consumption_data 
            WHERE timestamp >= ? AND papp > ? 
            ORDER BY timestamp DESC
        ");
        $stmt->execute([$oneHourAgo->format('Y-m-d H:i:s'), $seuilPuissance]);
        $exceedances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($exceedances as $exceedance) {
            // Éviter les doublons : ne pas alerter si une alerte similaire a été envoyée dans la dernière heure
            if (!$this->wasRecentAlertSent('power', $exceedance['papp'], 60)) {
                $alerts[] = [
                    'type' => 'power',
                    'severity' => 'high',
                    'title' => '⚡ Alerte de puissance élevée',
                    'message' => "Puissance de {$exceedance['papp']} W détectée à " . (new DateTime($exceedance['timestamp']))->format('H:i') . " (seuil : {$seuilPuissance} W)",
                    'timestamp' => $exceedance['timestamp'],
                    'value' => $exceedance['papp'],
                    'threshold' => $seuilPuissance
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Vérifie la consommation journalière (hier)
     */
    private function checkDailyConsumption(int $seuilJournalier): ?array
    {
        $yesterday = new DateTime();
        $yesterday->modify('-1 day');
        $yesterdayStart = clone $yesterday;
        $yesterdayStart->setTime(0, 0, 0);
        $yesterdayEnd = clone $yesterday;
        $yesterdayEnd->setTime(23, 59, 59);
        
        // Éviter les doublons : ne pas alerter si une alerte a déjà été envoyée pour hier
        if ($this->wasRecentAlertSent('daily', $yesterday->format('Y-m-d'), 1440)) { // 1440 minutes = 24h
            return null;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT hchc, hchp 
            FROM consumption_data 
            WHERE timestamp BETWEEN ? AND ? 
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$yesterdayStart->format('Y-m-d H:i:s'), $yesterdayEnd->format('Y-m-d H:i:s')]);
        $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($dailyData)) {
            return null;
        }
        
        $first = $dailyData[0];
        $last = $dailyData[count($dailyData) - 1];
        
        $consoHC = (float)$last['hchc'] - (float)$first['hchc'];
        $consoHP = (float)$last['hchp'] - (float)$first['hchp'];
        $totalConso = $consoHC + $consoHP;
        
        if ($totalConso > $seuilJournalier) {
            return [
                'type' => 'daily',
                'severity' => 'critical',
                'title' => '📊 Alerte de consommation journalière',
                'message' => "Consommation d'hier : " . round($totalConso, 2) . " kWh (seuil : {$seuilJournalier} kWh)",
                'timestamp' => $yesterday->format('Y-m-d H:i:s'),
                'value' => round($totalConso, 2),
                'threshold' => $seuilJournalier
            ];
        }
        
        return null;
    }
    
    /**
     * Vérifie si une alerte similaire a été envoyée récemment
     */
    private function wasRecentAlertSent(string $type, $value, int $minutesAgo): bool
    {
        $since = new DateTime();
        $since->modify("-{$minutesAgo} minutes");
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM alerts_log 
            WHERE type = ? AND value = ? AND created_at >= ?
        ");
        $stmt->execute([$type, $value, $since->format('Y-m-d H:i:s')]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Envoie un email d'alerte
     */
    private function sendAlertEmail(array $alert): bool
    {
        if (!$this->config['enabled'] || !$this->config['email']) {
            echo "[" . date('Y-m-d H:i:s') . "] Alerte non envoyée : email désactivé ou non configuré\n";
            return false;
        }
        
        $to = $this->config['email'];
        $subject = $alert['title'];
        $message = $this->buildEmailMessage($alert);
        $headers = [
            'From: noreply@statelec.local',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Construit le message email
     */
    private function buildEmailMessage(array $alert): string
    {
        $severityColor = [
            'high' => '#f97316',
            'critical' => '#dc2626'
        ][$alert['severity']] ?? '#6b7280';
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .alert-box { border-left: 4px solid {$severityColor}; background: #fef2f2; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; }
                .btn { display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔔 Statelec</h1>
                    <p>Alerte de consommation électrique</p>
                </div>
                <div class='content'>
                    <div class='alert-box'>
                        <h3>{$alert['title']}</h3>
                        <p><strong>Détail :</strong> {$alert['message']}</p>
                        <p><strong>Date :</strong> " . (new DateTime($alert['timestamp']))->format('d/m/Y H:i') . "</p>
                    </div>
                    <p>Cette alerte a été générée automatiquement par votre système de suivi de consommation.</p>
                    <a href='http://localhost/statelec2/statelec-gemini/alertes' class='btn'>Voir les détails</a>
                </div>
                <div class='footer'>
                    <p>Statelec - Système de suivi de consommation électrique</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Enregistre l'alerte dans les logs
     */
    private function logAlert(array $alert): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO alerts_log (type, severity, title, message, value, threshold, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $alert['type'],
            $alert['severity'],
            $alert['title'],
            $alert['message'],
            $alert['value'],
            $alert['threshold'],
            $alert['timestamp']
        ]);
    }
}