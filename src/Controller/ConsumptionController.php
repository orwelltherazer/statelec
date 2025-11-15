<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use PDOException;

class ConsumptionController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Gère GET /api/consumption
     */
    public function getConsumptionData(): void
    {
        try {
            $startDate = $_GET['start'] ?? null; // Changed from startDate to start
            $endDate = $_GET['end'] ?? null;     // Changed from endDate to end

            if ($startDate && $endDate) {
                // Compare against the 'timestamp' column for full datetime range
                $stmt = $this->pdo->prepare("SELECT * FROM consumption_data WHERE timestamp BETWEEN ? AND ? ORDER BY timestamp ASC");
                $stmt->execute([$startDate, $endDate]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->pdo->query("SELECT * FROM consumption_data ORDER BY timestamp ASC");
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            header('Content-Type: application/json');
            echo json_encode($data);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur de base de données: " . $e->getMessage()]);
        }
    }

    /**
     * Gère GET /api/consumption/day/{date}
     */
    public function getConsumptionByDay(string $date): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM consumption_data WHERE DATE(timestamp) = ? ORDER BY timestamp ASC");
            $stmt->execute([$date]);
            $data = $stmt->fetchAll();

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur de base de données: " . $e->getMessage()]);
        }
    }

    /**
     * Gère POST /api/consumption
     */
    public function saveConsumptionData(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON']);
                return;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO consumption_data
                (timestamp, papp, hchc, hchp, ptec)
                VALUES (:timestamp, :papp, :hchc, :hchp, :ptec)
                ON DUPLICATE KEY UPDATE
                papp = VALUES(papp),
                hchc = VALUES(hchc),
                hchp = VALUES(hchp),
                ptec = VALUES(ptec)
            ");

            $stmt->execute([
                ':timestamp' => $input['timestamp'],
                ':papp' => $input['papp'],
                ':hchc' => $input['hchc'] ?? null,
                ':hchp' => $input['hchp'] ?? null,
                ':ptec' => $input['ptec'] ?? null
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur de base de données: " . $e->getMessage()]);
        }
    }

    /**
     * Gère GET /api/consumption/count
     */
    public function countRecords(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM consumption_data");
            $result = $stmt->fetch();
            
            header('Content-Type: application/json');
            echo json_encode($result);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur de base de données: " . $e->getMessage()]);
        }
    }

    /**
     * Gère GET /api/consumption/paginated
     */
    public function getPaginatedData(): void
    {
        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;

            $stmt = $this->pdo->prepare("SELECT * FROM consumption_data ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll();

            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM consumption_data");
            $totalCount = (int)$countStmt->fetchColumn();

            $response = [
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'totalPages' => ceil($totalCount / $limit)
                ]
            ];

            header('Content-Type: application/json');
            echo json_encode($response);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur de base de données: " . $e->getMessage()]);
        }
    }
}
