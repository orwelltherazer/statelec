<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Statelec\Service\Database;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Configuration du logging pour les scripts cron
ini_set('error_log', dirname(__DIR__) . '/cron/cron.log');
ini_set('log_errors', '1');
ini_set('error_reporting', E_ALL);

// Initialize database connection
try {
    $pdo = Database::getInstance();
} catch (\PDOException $e) {
    error_log("CRON ERROR: Database connection failed: " . $e->getMessage());
    echo "Task failed: Database connection failed.\n";
    exit(1);
}

// --- Helper function to get settings ---
function getSetting(PDO $pdo, string $key, $defaultValue = null)
{
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = :key");
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? json_decode($value, true) : $defaultValue;
}

// --- Main data acquisition logic ---
try {
    // Get API URL from settings
    $apiUrl = getSetting($pdo, 'apiUrl');
    if (!$apiUrl) {
        error_log("CRON ERROR: API URL not configured in settings.");
        echo "Task failed: API URL not configured.\n";
        exit(1);
    }

    // Get field mappings from settings
    $fieldPapp = getSetting($pdo, 'fieldPapp', 'field1');
    $fieldIinst = getSetting($pdo, 'fieldIinst', 'field4');
    $fieldPtec = getSetting($pdo, 'fieldPtec', 'field7');
    $fieldHchc = getSetting($pdo, 'fieldHchc', 'field2');
    $fieldHchp = getSetting($pdo, 'fieldHchp', 'field3');

    // Fetch data from the external API (last 20 records)
    $apiUrlWithParams = $apiUrl . (strpos($apiUrl, '?') !== false ? '&' : '?') . 'results=20';

    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Statelec-Cron/1.0'
        ]
    ]);

    $response = file_get_contents($apiUrlWithParams, false, $context);

    if ($response === false) {
        error_log("CRON ERROR: Failed to fetch data from API: {$apiUrl}");
        echo "Task failed: Failed to fetch data from API.\n";
        exit(1);
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("CRON ERROR: Invalid JSON response from API: " . json_last_error_msg());
        echo "Task failed: Invalid JSON response from API.\n";
        exit(1);
    }

    // Assuming ThingSpeak format: { "channel": {}, "feeds": [{ "field1": "...", "created_at": "..." }] }
    if (!isset($data['feeds']) || empty($data['feeds'])) {
        error_log("CRON ERROR: API response does not contain 'feeds' or it's empty");
        echo "Task failed: API response does not contain feeds.\n";
        exit(1);
    }

    $feeds = array_slice($data['feeds'], -20); // Take last 20 records maximum
    $processedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;

    // Process each feed record
    foreach ($feeds as $feed) {
        $timestamp = $feed['created_at'] ?? (new DateTime())->format('Y-m-d H:i:s');
        $papp = $feed[$fieldPapp] ?? null;
        $ptec = $feed[$fieldPtec] ?? null;
        $hchc = $feed[$fieldHchc] ?? null;
        $hchp = $feed[$fieldHchp] ?? null;

        // Validate essential data
        if ($papp === null || $hchc === null || $hchp === null) {
            error_log("CRON WARNING: Skipping record {$timestamp} - missing essential data. PAPP: {$papp}, HCHC: {$hchc}, HCHP: {$hchp}");
            $errorCount++;
            continue;
        }

        // Check if this timestamp already exists to prevent duplicates
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM consumption_data WHERE timestamp = :timestamp");
        $stmt->execute([':timestamp' => $timestamp]);
        if ($stmt->fetchColumn() > 0) {
            $skippedCount++;
            continue;
        }

        try {
            // Insert data into the database
            $stmt = $pdo->prepare("
                INSERT INTO consumption_data (timestamp, papp, ptec, hchc, hchp)
                VALUES (:timestamp, :papp, :ptec, :hchc, :hchp)
                ON DUPLICATE KEY UPDATE
                papp = VALUES(papp), ptec = VALUES(ptec), hchc = VALUES(hchc), hchp = VALUES(hchp)
            ");

            $stmt->execute([
                ':timestamp' => $timestamp,
                ':papp' => $papp,
                ':ptec' => $ptec,
                ':hchc' => $hchc,
                ':hchp' => $hchp,
            ]);

            $processedCount++;
        } catch (Exception $e) {
            error_log("CRON ERROR: Failed to save record {$timestamp}: " . $e->getMessage());
            $errorCount++;
        }
    }

    error_log("CRON SUCCESS: Processed {$processedCount} records, skipped {$skippedCount} duplicates, {$errorCount} errors.");
    echo "Task completed successfully. Processed {$processedCount} records, skipped {$skippedCount} duplicates, {$errorCount} errors.\n";
    exit(0);

} catch (Exception $e) {
    error_log("CRON FATAL ERROR: " . $e->getMessage());
    echo "Task failed: " . $e->getMessage() . "\n";
    exit(1);
}
