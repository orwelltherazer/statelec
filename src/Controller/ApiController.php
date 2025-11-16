<?php

declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\Database;
use PDO;
use Exception;

class ApiController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        if (!$this->pdo) {
            throw new Exception("Database connection failed for ApiController.");
        }
    }

    public function receiveData(): void
    {
        // 1. Check if the request method is GET
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed: Only GET requests are accepted.']);
            return;
        }

        // 2. Check API Key configuration (Security)
        $expectedApiKey = $_ENV['API_KEY'] ?? null;
        if (!$expectedApiKey || $expectedApiKey === 'your_super_secret_api_key') {
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => 'API not configured: Please set API_KEY in .env file.']);
            return;
        }

        // 3. Get data from query parameters
        $data = $_GET;

        // 5. Check for API Key (Security)
        $apiKey = $data['api_key'] ?? '';

        if (empty($apiKey) || $apiKey !== $expectedApiKey) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid or missing API Key.']);
            return;
        }

        // 4. Validate and format the timestamp
        if (!isset($data['timestamp'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => "Missing required field: timestamp."]);
            return;
        }
        try {
            $date = new \DateTime($data['timestamp']);
            $date->setTimezone(new \DateTimeZone('UTC')); // Standardize to UTC
            $timestamp = $date->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => "Invalid timestamp format. Please use a valid date-time format."]);
            return;
        }

        // 5. Validate other essential data fields
        $requiredFields = ['papp', 'hchc', 'hchp'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                http_response_code(400); // Bad Request
                echo json_encode(['status' => 'error', 'message' => "Missing required field: {$field}."]);
                return;
            }
        }

        // Extract data, with ptec being optional
        $papp = (int) $data['papp'];
        $hchc = (float) $data['hchc'];
        $hchp = (float) $data['hchp'];
        $ptec = isset($data['ptec']) ? (int) $data['ptec'] : null;

        try {
            // 6. Insert data into the database
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

            http_response_code(200); // OK
            echo json_encode(['status' => 'success', 'message' => 'Data received and stored successfully.']);

        } catch (Exception $e) {
            error_log("API ERROR: Failed to save record {$timestamp}: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => 'Failed to store data.', 'details' => $e->getMessage()]);
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
