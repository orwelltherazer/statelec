<?php

declare(strict_types=1);

namespace Statelec\Service;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): ?PDO
    {
        if (self::$instance === null) {
            try {
                $dbHost = $_ENV['DB_HOST'];
                $dbName = $_ENV['DB_NAME'];
                $dbUser = $_ENV['DB_USER'];
                $dbPass = $_ENV['DB_PASSWORD'];
                $dbPort = $_ENV['DB_PORT'] ?? 3306;

                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

                self::$instance = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::createTables();
            } catch (PDOException $e) {
                // Logger l'erreur mais ne pas exposer les détails
                error_log('Database connection error: ' . $e->getMessage());
                self::$instance = null;
            }
        }

        return self::$instance;
    }

    public static function createTables(): void
    {
        $pdo = self::getInstance();
        if (!$pdo) {
            return;
        }
        
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS consumption_data (
                  timestamp VARCHAR(255) PRIMARY KEY,
                  papp INT,
                  hchc DECIMAL(10,2),
                  hchp DECIMAL(10,2),
                  ptec INT,
                  INDEX idx_consumption_papp (papp),
                  INDEX idx_consumption_hchc (hchc),
                  INDEX idx_consumption_hchp (hchp),
                  INDEX idx_consumption_ptec (ptec)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                   `key` VARCHAR(255) PRIMARY KEY,
                   value TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS alerts_log (
                   id INT AUTO_INCREMENT PRIMARY KEY,
                   type VARCHAR(50) NOT NULL COMMENT 'Type d''alerte (power, daily)',
                   severity ENUM('high','critical') NOT NULL COMMENT 'Sévérité',
                   title VARCHAR(255) NOT NULL COMMENT 'Titre de l''alerte',
                   message TEXT NOT NULL COMMENT 'Message détaillé',
                   value DECIMAL(10,2) DEFAULT NULL COMMENT 'Valeur associée',
                   threshold DECIMAL(10,2) DEFAULT NULL COMMENT 'Seuil dépassé',
                   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            error_log('Table creation error: ' . $e->getMessage());
        }
    }

    /**
     * Empêcher l'instanciation directe
     */
    private function __construct()
    {
    }

    /**
     * Empêcher le clonage
     */
    private function __clone()
    {
    }

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}
