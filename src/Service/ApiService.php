<?php
declare(strict_types=1);

namespace Statelec\Service;

use PDO;
use Exception;

class ApiService {
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function receive(array $data): array
    {
        // API Key validation (assume method check is done by controller)
        $apiKey = $data['api_key'] ?? '';
        $expectedApiKey = $_ENV['API_KEY'] ?? 'your_super_secret_api_key';
        if (empty($apiKey) || $apiKey !== $expectedApiKey) {
            http_response_code(401);
            return ['http' => 401, 'payload' => ['status' => 'error', 'message' => 'Unauthorized: Invalid or missing API Key.']];
        }

        // timestamp
        if (!isset($data['timestamp'])) {
            http_response_code(400);
            return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'Missing required field: timestamp.']];
        }
        try {
            $date = new \DateTime($data['timestamp']);
            $date->setTimezone(new \DateTimeZone('UTC'));
            $timestamp = $date->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            http_response_code(400);
            return ['http' => 400, 'payload' => ['status' => 'error', 'message' => 'Invalid timestamp format. Please use a valid date-time format.']];
        }

        // required fields
        $requiredFields = ['papp', 'hchc', 'hchp'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                return ['http' => 400, 'payload' => ['status' => 'error', 'message' => "Missing required field: {$field}."]];
            }
        }

        $papp = (int) $data['papp'];
        $hchc = (float) $data['hchc'];
        $hchp = (float) $data['hchp'];
        $ptec = isset($data['ptec']) ? (int) $data['ptec'] : null;

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO consumption_data (timestamp, papp, ptec, hchc, hchp)
                 VALUES (:timestamp, :papp, :ptec, :hchc, :hchp)
                 ON DUPLICATE KEY UPDATE
                 papp = VALUES(papp), ptec = VALUES(ptec), hchc = VALUES(hchc), hchp = VALUES(hchp)"
            );
            $stmt->execute([
                ':timestamp' => $timestamp,
                ':papp' => $papp,
                ':ptec' => $ptec,
                ':hchc' => $hchc,
                ':hchp' => $hchp,
            ]);
            http_response_code(200);
            return ['http' => 200, 'payload' => ['status' => 'success', 'message' => 'Data received and stored successfully.']];
        } catch (Exception $e) {
            error_log("API ERROR: Failed to save record {$timestamp}: " . $e->getMessage());
            http_response_code(500);
            return ['http' => 500, 'payload' => ['status' => 'error', 'message' => 'Failed to store data.', 'details' => $e->getMessage()]];
        }
    }
}
