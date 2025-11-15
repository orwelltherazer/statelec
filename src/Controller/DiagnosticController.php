<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use DateTime;
use DateTimeZone;

class DiagnosticController
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    public function showDiagnostic(): array
    {
        if (!$this->pdo) {
            return [
                'page_title' => 'Diagnostic',
                'currentPage' => 'diagnostic',
                'db_error' => true,
                'basePath' => $_ENV['BASE_PATH'] ?? '/',
                'theme' => 'light'
            ];
        }

        $data = $this->getDiagnosticData();
        return [
            'page_title' => 'Diagnostic',
            'currentPage' => 'diagnostic',
            'dbStatus' => $data['dbStatus'],
            'recordCount' => $data['recordCount'],
            'lastRecordTimestamp' => $data['lastRecordTimestamp'],
            'theme' => 'light' // TODO: Implement theme switching
        ];
    }

    private function getDiagnosticData(): array
    {
        if (!$this->pdo) {
            return [
                'dbStatus' => 'error',
                'recordCount' => 0,
                'lastRecordTimestamp' => 'Connexion impossible'
            ];
        }

        $dbStatus = 'ok';
        $recordCount = 0;
        $lastRecordTimestamp = 'N/A';

        try {
            // Check DB connection
            $this->pdo->query("SELECT 1");

            // Get record count
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM consumption_data");
            $recordCount = $stmt->fetchColumn();

            // Get last record timestamp
            $stmt = $this->pdo->query("SELECT MAX(timestamp) FROM consumption_data");
            $lastRecordTimestamp = $stmt->fetchColumn();
            if ($lastRecordTimestamp) {
                $lastRecordTimestamp = (new DateTime($lastRecordTimestamp))->format('Y-m-d H:i:s');
            } else {
                $lastRecordTimestamp = 'Aucun enregistrement';
            }

        } catch (\PDOException $e) {
            $dbStatus = 'error';
            // Log the error for debugging
            error_log("Diagnostic DB Error: " . $e->getMessage());
        }

        return [
            'dbStatus' => $dbStatus,
            'recordCount' => $recordCount,
            'lastRecordTimestamp' => $lastRecordTimestamp
        ];
    }

    public function getPaginatedData(): void
    {
        header('Content-Type: application/json');

        if (!$this->pdo) {
            http_response_code(500);
            echo json_encode(['error' => 'Connexion à la base de données impossible']);
            return;
        }

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 20;
        $offset = ($page - 1) * $limit;

        try {
            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM consumption_data");
            $totalRecords = $countStmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT * FROM consumption_data ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalPages = ceil($totalRecords / $limit);

            echo json_encode([
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalRecords,
                    'totalPages' => $totalPages
                ]
            ]);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la récupération des données paginées: ' . $e->getMessage()]);
        }
    }

    public function fetchHistoricalData(): void
    {
        header('Content-Type: application/json');

        if (!$this->pdo) {
            http_response_code(500);
            echo json_encode(['error' => 'Connexion à la base de données impossible']);
            return;
        }

        try {
            // Get API URL from settings
            $apiUrl = $this->getSetting('apiUrl');
            if (!$apiUrl) {
                http_response_code(400);
                echo json_encode(['error' => 'API URL not configured in settings.']);
                return;
            }

            // Get field mappings from settings
            $fieldPapp = $this->getSetting('fieldPapp', 'field1');
            $fieldIinst = $this->getSetting('fieldIinst', 'field4');
            $fieldPtec = $this->getSetting('fieldPtec', 'field7');
            $fieldHchc = $this->getSetting('fieldHchc', 'field2');
            $fieldHchp = $this->getSetting('fieldHchp', 'field3');

            // Fetch data from the external API (max 8000 records)
            $apiUrlWithParams = $apiUrl . (strpos($apiUrl, '?') !== false ? '&' : '?') . 'results=8000';

            $context = stream_context_create([
                'http' => [
                    'timeout' => 60, // Longer timeout for large fetch
                    'user_agent' => 'Statelec-Cron/1.0'
                ]
            ]);

            $response = file_get_contents($apiUrlWithParams, false, $context);

            if ($response === false) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to fetch data from API.']);
                return;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(500);
                echo json_encode(['error' => 'Invalid JSON response from API.']);
                return;
            }

            if (!isset($data['feeds']) || empty($data['feeds'])) {
                http_response_code(400);
                echo json_encode(['error' => 'API response does not contain feeds or it\'s empty.']);
                return;
            }

            $feeds = $data['feeds'];
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
                    $errorCount++;
                    continue;
                }

                // Check if this timestamp already exists to prevent duplicates
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM consumption_data WHERE timestamp = :timestamp");
                $stmt->execute([':timestamp' => $timestamp]);
                if ($stmt->fetchColumn() > 0) {
                    $skippedCount++;
                    continue;
                }

                try {
                    // Insert data into the database
                    $stmt = $this->pdo->prepare("
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
                } catch (\Exception $e) {
                    $errorCount++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Historical data fetch completed. Processed: {$processedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}."
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'An error occurred while fetching historical data: ' . $e->getMessage()]);
        }
    }

    private function getSetting(string $key, $defaultValue = null)
    {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE `key` = :key");
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? json_decode($value, true) : $defaultValue;
    }
}
