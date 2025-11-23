<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\Logger;

/**
 * Middleware de limitation de taux (Rate Limiting)
 *
 * Protège contre les abus en limitant le nombre de requêtes par IP
 */
class RateLimitMiddleware
{
    private array $config;
    private string $storageFile;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->storageFile = __DIR__ . '/../../cache/rate_limit.json';
    }

    /**
     * Vérifie la limite de taux
     */
    public function check(int $maxRequests = null, int $windowSeconds = null): bool
    {
        if (!($this->config['security']['rate_limit']['enabled'] ?? true)) {
            return true;
        }

        $maxRequests = $maxRequests ?? $this->config['security']['rate_limit']['max_requests'] ?? 60;
        $windowSeconds = $windowSeconds ?? $this->config['security']['rate_limit']['window_seconds'] ?? 60;

        $ip = $this->getClientIp();
        $now = time();

        // Charger les données de rate limiting
        $data = $this->loadData();

        // Nettoyer les anciennes entrées
        $data = $this->cleanOldEntries($data, $now, $windowSeconds);

        // Vérifier le nombre de requêtes pour cette IP
        if (!isset($data[$ip])) {
            $data[$ip] = [];
        }

        // Compter les requêtes dans la fenêtre
        $requestsInWindow = count(array_filter($data[$ip], function ($timestamp) use ($now, $windowSeconds) {
            return $timestamp > ($now - $windowSeconds);
        }));

        if ($requestsInWindow >= $maxRequests) {
            Logger::warning('Rate limit exceeded', [
                'ip' => $ip,
                'requests' => $requestsInWindow,
                'limit' => $maxRequests,
                'window' => $windowSeconds
            ]);

            $this->sendRateLimitResponse($windowSeconds);
            return false;
        }

        // Ajouter cette requête
        $data[$ip][] = $now;

        // Sauvegarder
        $this->saveData($data);

        return true;
    }

    /**
     * Récupère l'IP du client
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR'            // Direct
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Si plusieurs IPs (proxy chain), prendre la première
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Charge les données de rate limiting
     */
    private function loadData(): array
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }

        $content = file_get_contents($this->storageFile);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Sauvegarde les données de rate limiting
     */
    private function saveData(array $data): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->storageFile, json_encode($data));
    }

    /**
     * Nettoie les anciennes entrées
     */
    private function cleanOldEntries(array $data, int $now, int $windowSeconds): array
    {
        foreach ($data as $ip => $timestamps) {
            // Garder uniquement les timestamps dans la fenêtre
            $data[$ip] = array_filter($timestamps, function ($timestamp) use ($now, $windowSeconds) {
                return $timestamp > ($now - $windowSeconds * 2); // Garder 2x la fenêtre pour historique
            });

            // Supprimer les IPs sans requêtes récentes
            if (empty($data[$ip])) {
                unset($data[$ip]);
            }
        }

        return $data;
    }

    /**
     * Envoie une réponse de rate limit dépassé
     */
    private function sendRateLimitResponse(int $retryAfter): void
    {
        http_response_code(429);
        header("Retry-After: $retryAfter");
        header('Content-Type: application/json');

        echo json_encode([
            'error' => 'Too many requests',
            'message' => 'Trop de requêtes. Veuillez réessayer dans quelques instants.',
            'retry_after' => $retryAfter
        ]);

        exit;
    }

    /**
     * Rate limiting spécifique pour les tentatives de login
     */
    public function checkLoginAttempts(string $identifier = null): bool
    {
        // 5 tentatives par 15 minutes
        $maxAttempts = 5;
        $windowSeconds = 900; // 15 minutes

        $ip = $identifier ?? $this->getClientIp();

        return $this->check($maxAttempts, $windowSeconds);
    }

    /**
     * Rate limiting pour l'API
     */
    public function checkApiLimit(): bool
    {
        $maxRequests = $this->config['api']['rate_limit']['max_requests'] ?? 100;
        $windowSeconds = $this->config['api']['rate_limit']['window_seconds'] ?? 60;

        return $this->check($maxRequests, $windowSeconds);
    }
}
