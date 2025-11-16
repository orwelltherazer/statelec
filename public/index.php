<?php

declare(strict_types=1);

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Charger les services manuellement
require_once dirname(__DIR__) . '/src/Service/Database.php';
require_once dirname(__DIR__) . '/src/Service/ErrorHandler.php';
require_once dirname(__DIR__) . '/src/Middleware/ErrorMiddleware.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Statelec\Service\Database;
use Statelec\Middleware\ErrorMiddleware;
use Statelec\Controller\ConsumptionController;
use Statelec\Controller\SettingsController;
use Statelec\Controller\SystemController;
use Statelec\Controller\DashboardController;
use Statelec\Controller\CostController;
use Statelec\Controller\AlertsController;
use Statelec\Controller\AnalysisController;
use Statelec\Controller\DiagnosticController;
use Statelec\Controller\HistoriqueController;
use Statelec\Controller\ApiController;

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Définir le fuseau horaire (configurable via .env)
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'Europe/Paris');

// Initialiser Twig
$loader = new FilesystemLoader(dirname(__DIR__) . '/templates');
$twig = new Environment($loader);

// Routage
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Calculer dynamiquement le basePath
// Exemple: /statelec2/statelec-gemini/public/index.php -> /statelec2/statelec-gemini/public
$basePath = str_replace(basename($scriptName), '', $scriptName);
// Assurer que le basePath se termine par un slash
if (substr($basePath, -1) !== '/') {
    $basePath .= '/';
}

// Supprimer la query string
if (($pos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $pos);
}

// Supprimer le base path de l'URL
if ($basePath !== '/' && strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}
if ($requestUri === '') $requestUri = '/';

// Ajouter le basePath comme variable globale à Twig
$twig->addGlobal('basePath', $basePath);

// Fonction helper pour gérer les routes de pages avec gestion d'erreur DB
function handlePageRoute($twig, $controllerClass, $method, $template) {
    try {
        $controller = new $controllerClass();
        $data = $controller->$method();

        if (isset($data['db_error']) && $data['db_error']) {
            echo $twig->render('pages/db_error.twig', $data);
        } else {
            echo $twig->render($template, $data);
        }
    } catch (Exception $e) {
        echo $twig->render('pages/db_error.twig', ['basePath' => $GLOBALS['basePath'] ?? '/']);
    }
}

// Définir le thème par défaut (le JavaScript le gérera dynamiquement)
$twig->addGlobal('theme', 'light');

// Enlever le chemin de base de l'URI pour le routage interne
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}
$requestUri = parse_url($requestUri, PHP_URL_PATH);
if ($requestUri === null || $requestUri === '') {
    $requestUri = '/';
} elseif ($requestUri[0] !== '/') {
    $requestUri = '/' . $requestUri;
}

// Gérer les requêtes API
if (strpos($requestUri, '/api/') === 0) {
    header('Content-Type: application/json');
    // Initialiser la base de données uniquement pour les appels API
    try {
        Database::getInstance();
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => "Erreur de connexion au serveur."]);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // Routage plus flexible pour l'API
    if ($requestUri === '/api/consumption' && $method === 'GET') {
        $controller = new ConsumptionController();
        $controller->getConsumptionData();
    } elseif ($requestUri === '/api/consumption' && $method === 'POST') {
        $controller = new ConsumptionController();
        $controller->saveConsumptionData();
    } elseif ($requestUri === '/api/data' && $method === 'GET') {
        $controller = new ApiController();
        $controller->receiveData();
    } elseif ($requestUri === '/api/consumption/count' && $method === 'GET') {
        $controller = new ConsumptionController();
        $controller->countRecords();
    } elseif ($requestUri === '/api/consumption/paginated' && $method === 'GET') {
        $controller = new ConsumptionController();
        $controller->getPaginatedData();
    } elseif (preg_match('/^\/api\/consumption\/day\/(\d{4}-\d{2}-\d{2})$/', $requestUri, $matches) && $method === 'GET') {
        $controller = new ConsumptionController();
        $controller->getConsumptionByDay($matches[1]);
    } elseif (preg_match('/^\/api\/settings\/(.+)$/', $requestUri, $matches) && $method === 'GET') {
        $controller = new SettingsController();
        $controller->getSetting($matches[1]);
    } elseif (preg_match('/^\/api\/settings\/(.+)$/', $requestUri, $matches) && $method === 'POST') {
        $controller = new SettingsController();
        $controller->saveSetting($matches[1]);
    } elseif ($requestUri === '/api/status' && $method === 'GET') {
        $controller = new SystemController();
        $controller->getStatus();
    } elseif ($requestUri === '/api/diagnostic/paginated' && $method === 'GET') {
        $controller = new DiagnosticController();
        $controller->getPaginatedData();
    } elseif ($requestUri === '/api/diagnostic/fetch-historical' && $method === 'POST') {
        $controller = new DiagnosticController();
        $controller->fetchHistoricalData();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint API non trouvé']);
    }
    exit;
}

// Gérer les requêtes de pages web (Frontend PHP/Twig)
switch ($requestUri) {
    case '/':
    case '/dashboard':
        handlePageRoute($twig, DashboardController::class, 'showDashboard', 'pages/dashboard.twig');
        break;
    case '/historique':
        handlePageRoute($twig, HistoriqueController::class, 'showHistorique', 'pages/historique.twig');
        break;
    case '/cout':
        handlePageRoute($twig, CostController::class, 'showCost', 'pages/cout.twig');
        break;
    case '/alertes':
        handlePageRoute($twig, AlertsController::class, 'showAlerts', 'pages/alertes.twig');
        break;
    case '/analyse':
        handlePageRoute($twig, AnalysisController::class, 'showAnalysis', 'pages/analyse.twig');
        break;
    case '/parametres':
        handlePageRoute($twig, SettingsController::class, 'showSettings', 'pages/parametres.twig');
        break;
    case '/diagnostic':
        handlePageRoute($twig, DiagnosticController::class, 'showDiagnostic', 'pages/diagnostic.twig');
        break;
    // Ajoutez d'autres routes ici au fur et à mesure
    case '/error':
        echo $twig->render('pages/error.twig', ErrorMiddleware::getErrorPageData());
        break;
    default:
        http_response_code(404);
        try {
            echo $twig->render('404.twig', ['page_title' => 'Page non trouvée']);
        } catch (Exception $e) {
            echo '<h1>Page non trouvée</h1><p>La page que vous cherchez n\'existe pas.</p>';
        }
        break;
}
