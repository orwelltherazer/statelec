<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use PDOException;

class SystemController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Gère POST /api/reset-database
     */
    public function resetDatabase(): void
    {
        try {
            // Close existing connection if any (though PDO handles this implicitly on script end)
            // Re-initialize to ensure tables are created if they don't exist
            Database::createTables(); // Ensure tables exist before clearing

            // Clear consumption_data table
            $this->pdo->exec("DELETE FROM consumption_data");
            // Clear settings table
            $this->pdo->exec("DELETE FROM settings");

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Base de données MySQL réinitialisée"]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur lors de la réinitialisation de la base de données: " . $e->getMessage()]);
        }
    }

    /**
     * Gère GET /api/status
     */
    public function getStatus(): void
    {
        try {
            // Attempt to get a connection to verify database status
            Database::getInstance();
            $dbStatus = "ok";
        } catch (PDOException $e) {
            $dbStatus = "error";
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'database' => $dbStatus]);
    }
}
