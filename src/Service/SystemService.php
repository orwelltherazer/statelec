<?php
declare(strict_types=1);

namespace Statelec\\Service;

use PDO;
use PDOException;

class SystemService {
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getStatus(): array
    {
        $dbStatus = 'error';
        if ($this->pdo) {
            try {
                $this->pdo->query("SELECT 1");
                $dbStatus = 'ok';
            } catch (PDOException $e) {
                $dbStatus = 'error';
            }
        }
        return [
            'status' => 'ok',
            'database' => $dbStatus
        ];
    }
}
