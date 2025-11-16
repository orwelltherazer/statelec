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
            'moduleStatus' => $data['moduleStatus']
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

            // Check if data received recently (last 60 seconds)
            $sixtySecondsAgo = (new DateTime())->modify('-1 minute');
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM consumption_data WHERE timestamp >= ?");
            $stmt->execute([$sixtySecondsAgo->format('Y-m-d H:i:s')]);
            $recentRecords = $stmt->fetchColumn();
            $moduleStatus = $recentRecords > 0 ? 'online' : 'offline';

        } catch (\PDOException $e) {
            $dbStatus = 'error';
            // Log the error for debugging
            error_log("Diagnostic DB Error: " . $e->getMessage());
        }

        return [
            'dbStatus' => $dbStatus,
            'recordCount' => $recordCount,
            'lastRecordTimestamp' => $lastRecordTimestamp,
            'moduleStatus' => $moduleStatus
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
}
