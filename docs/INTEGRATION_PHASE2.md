# Intégration Phase 2 - Authentification

## Instructions pour intégrer l'authentification dans index.php

### Option 1 : Intégration complète (recommandée après tests)

Remplacer le début de `public/index.php` (lignes 1-40 environ) par :

```php
<?php

declare(strict_types=1);

// Charger le bootstrap V2
$config = require dirname(__DIR__) . '/src/bootstrap.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Statelec\Service\Database;
use App\Router as AuthRouter;

// Charger les contrôleurs existants
require_once dirname(__DIR__) . '/src/Service/Database.php';
require_once dirname(__DIR__) . '/src/Middleware/ErrorMiddleware.php';

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

// Initialiser Twig
$loader = new FilesystemLoader(dirname(__DIR__) . '/templates');
$twig = new Environment($loader);

// Récupérer la connexion DB
$db = Database::getInstance();

// Initialiser le routeur d'authentification
$authRouter = new AuthRouter($twig, $db, $config);

// [Garder le reste du code de routage existant]
```

Puis, juste après l'initialisation du routeur, ajouter :

```php
// Gérer les routes d'authentification
$method = $_SERVER['REQUEST_METHOD'];
if ($authRouter->handle($requestUri, $method)) {
    exit; // Route gérée par le routeur d'authentification
}
```

### Option 2 : Intégration progressive (pour test)

Ajouter au début de `public/index.php`, juste après `require_once dirname(__DIR__) . '/vendor/autoload.php';` :

```php
// === PHASE 2: AUTHENTIFICATION (OPTIONNEL) ===
if (file_exists(dirname(__DIR__) . '/src/Router.php')) {
    $config = require dirname(__DIR__) . '/config/app.php';

    // Activer l'authentification si le feature flag est activé
    if ($config['features']['authentication'] ?? false) {
        require_once dirname(__DIR__) . '/src/Router.php';
        require_once dirname(__DIR__) . '/src/Controller/BaseController.php';
        require_once dirname(__DIR__) . '/src/Controller/AuthController.php';
        require_once dirname(__DIR__) . '/src/Controller/UserController.php';
        require_once dirname(__DIR__) . '/src/Service/AuthService.php';
        require_once dirname(__DIR__) . '/src/Service/UserService.php';
        require_once dirname(__DIR__) . '/src/Service/Logger.php';
        require_once dirname(__DIR__) . '/src/Middleware/AuthMiddleware.php';
        require_once dirname(__DIR__) . '/src/Middleware/RateLimitMiddleware.php';

        $db = \Statelec\Service\Database::getInstance();
        $authRouter = new \App\Router($twig, $db, $config);

        // Tenter de gérer la route avec le routeur d'auth
        $method = $_SERVER['REQUEST_METHOD'];
        if ($authRouter->handle($requestUri, $method)) {
            exit; // Route gérée
        }
    }
}
// === FIN PHASE 2 ===
```

Cette méthode permet d'activer/désactiver l'authentification via le feature flag sans modifier le code existant.

### Option 3 : Activation manuelle (développement uniquement)

Créer un fichier `public/auth.php` séparé qui gère uniquement les routes d'authentification :

```php
<?php
// Fichier: public/auth.php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/src/bootstrap.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Statelec\Service\Database;
use App\Router as AuthRouter;

$loader = new FilesystemLoader(dirname(__DIR__) . '/templates');
$twig = new Environment($loader);
$db = Database::getInstance();

$authRouter = new AuthRouter($twig, $db, $config);

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if (!$authRouter->handle($requestUri, $method)) {
    http_response_code(404);
    echo "Page non trouvée";
}
```

Puis configurer le serveur web pour rediriger `/login`, `/register`, `/profile`, `/admin/*` vers `auth.php`.

## Protection des routes existantes

Pour protéger les routes existantes (dashboard, historique, etc.), ajouter au début de chaque section de route :

```php
// Avant de traiter la route dashboard
case '/dashboard':
    if ($config['features']['authentication'] ?? false) {
        $authRouter->protectRoute(); // Nécessite l'authentification
    }
    handlePageRoute($twig, DashboardController::class, 'showDashboard', 'pages/dashboard.twig');
    break;
```

Ou pour les routes admin :

```php
case '/admin':
    if ($config['features']['authentication'] ?? false) {
        $authRouter->protectAdminRoute(); // Nécessite admin
    }
    // Code existant
    break;
```

## Activer l'authentification

Modifier `config/app.php` :

```php
'features' => [
    'authentication' => true, // <-- Passer de false à true
    // ...
],
```

## Tester l'authentification

1. Appliquer la migration SQL (Phase 1)
2. Activer le feature flag
3. Accéder à `/login`
4. Se connecter avec : admin@statelec.local / admin123
5. Vérifier que vous êtes redirigé vers `/dashboard`
6. Accéder à `/admin/users` pour voir la liste des utilisateurs

## Débogage

Si vous rencontrez des erreurs :

1. Vérifier que toutes les tables ont été créées (Phase 1)
2. Vérifier que Monolog est installé : `composer install`
3. Consulter les logs : `logs/app-YYYY-MM-DD.log`
4. Vérifier les permissions des dossiers logs/, cache/, storage/

## Notes importantes

- Le compte admin par défaut est : `admin@statelec.local` / `admin123`
- Ce mot de passe DOIT être changé en production !
- Les sessions sont stockées dans la table `sessions`
- Le rate limiting utilise le dossier `cache/rate_limit.json`
- Les tokens CSRF sont générés automatiquement et vérifiés sur tous les formulaires
