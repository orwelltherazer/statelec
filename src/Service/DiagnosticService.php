<?php
declare(strict_types=1);

namespace Statelec\Service;

use PDO;
use DateTime;

class DiagnosticService {
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDiagnosticData(): array
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
            error_log("Diagnostic DB Error: " . $e->getMessage());
        }

        return [
            'dbStatus' => $dbStatus,
            'recordCount' => $recordCount,
            'lastRecordTimestamp' => $lastRecordTimestamp
        ];
    }

    public function exportDataAsCsv(int $limit = 1000): string
    {
        if (!$this->pdo) {
            return '';
        }
        $limit = (int) $limit;
        $stmt = $this->pdo->query("SELECT timestamp, papp, ptec, hchc, hchp FROM consumption_data ORDER BY timestamp DESC LIMIT {$limit}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $lines = [];
        $lines[] = 'timestamp,papp,ptec,hchc,hchp';
        foreach ($rows as $r) {
            $line = [
                $r['timestamp'],
                $r['papp'],
                $r['ptec'] ?? '',
                $r['hchc'],
                $r['hchp']
            ];
            $esc = array_map(function($val){
                if ($val === null) return '';
                $s = (string)$val;
                if (strpos($s, ',') !== false || strpos($s, '"') !== false) {
                    $s = '"' . str_replace('"','""',$s) . '"';
                }
                return $s;
            }, $line);
            $lines[] = implode(',', $esc);
        }
        return implode("\n", $lines);
    }

    public function clearData(): void
    {
        if ($this->pdo) {
            $this->pdo->exec('TRUNCATE TABLE consumption_data');
        }
    }
}
