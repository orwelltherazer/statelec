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
