<?php

/**
 * Script d'alerte automatique pour Statelec Gemini
 * À exécuter via cron toutes les heures
 * 
 * Exemple de configuration cron :
 * 0 * * * * /usr/bin/php /var/www/statelec2/statelec-gemini/scripts/alert_cron.php >> /var/log/statelec_alerts.log 2>&1
 */

// Charger l'autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Configuration du logging pour les scripts cron
ini_set('error_log', dirname(__DIR__) . '/cron/cron.log');
ini_set('log_errors', '1');
ini_set('error_reporting', E_ALL);

// Charger les services manuellement (pas d'autoloader dans ce projet)
require_once __DIR__ . '/../src/Service/Database.php';
require_once __DIR__ . '/../src/Service/AlertService.php';

use Statelec\Service\AlertService;

try {
    $alertService = new AlertService();
    $alertService->checkAndSendAlerts();
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Script terminé avec succès\n";