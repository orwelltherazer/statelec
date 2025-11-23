<?php

declare(strict_types=1);

/**
 * Script de validation Phase 1
 *
 * Ce script vérifie que l'infrastructure de la Phase 1 est correctement installée.
 */

echo "========================================\n";
echo "VALIDATION PHASE 1 - STATELEC V2\n";
echo "========================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Vérifier l'autoloader
echo "[1/8] Vérification de l'autoloader Composer...\n";
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $success[] = "✓ Autoloader Composer trouvé";
} else {
    $errors[] = "✗ Autoloader Composer introuvable. Exécutez 'composer install'";
}

// 2. Vérifier le fichier .env
echo "[2/8] Vérification du fichier .env...\n";
if (file_exists(__DIR__ . '/../.env')) {
    $success[] = "✓ Fichier .env trouvé";
} else {
    $warnings[] = "⚠ Fichier .env introuvable (optionnel en dev)";
}

// 3. Vérifier la configuration
echo "[3/8] Vérification de la configuration...\n";
if (file_exists(__DIR__ . '/../config/app.php')) {
    $config = require __DIR__ . '/../config/app.php';
    if (is_array($config) && isset($config['app'], $config['database'], $config['logging'])) {
        $success[] = "✓ Configuration chargée correctement";
        echo "   Version: {$config['app']['version']}\n";
        echo "   Environnement: {$config['app']['environment']}\n";
    } else {
        $errors[] = "✗ Configuration invalide";
    }
} else {
    $errors[] = "✗ Fichier de configuration introuvable";
}

// 4. Vérifier Monolog
echo "[4/8] Vérification de Monolog...\n";
if (class_exists('Monolog\Logger')) {
    $success[] = "✓ Monolog installé";
} else {
    $errors[] = "✗ Monolog non installé. Exécutez 'composer require monolog/monolog'";
}

// 5. Vérifier le service Logger
echo "[5/8] Vérification du service Logger...\n";
if (file_exists(__DIR__ . '/../src/Service/Logger.php')) {
    require_once __DIR__ . '/../src/Service/Logger.php';
    if (class_exists('App\Service\Logger')) {
        $success[] = "✓ Service Logger disponible";

        // Tester le logging
        try {
            \App\Service\Logger::init($config ?? []);
            \App\Service\Logger::info('Test de validation Phase 1');
            $success[] = "✓ Test de logging réussi";
        } catch (Exception $e) {
            $warnings[] = "⚠ Erreur lors du test de logging: " . $e->getMessage();
        }
    } else {
        $errors[] = "✗ Classe Logger introuvable";
    }
} else {
    $errors[] = "✗ Fichier Logger.php introuvable";
}

// 6. Vérifier le bootstrap
echo "[6/8] Vérification du bootstrap...\n";
if (file_exists(__DIR__ . '/../src/bootstrap.php')) {
    $success[] = "✓ Fichier bootstrap.php présent";
} else {
    $errors[] = "✗ Fichier bootstrap.php introuvable";
}

// 7. Vérifier les dossiers
echo "[7/8] Vérification des dossiers...\n";
$requiredDirs = [
    'config' => __DIR__ . '/../config',
    'logs' => __DIR__ . '/../logs',
    'cache' => __DIR__ . '/../cache',
    'storage' => __DIR__ . '/../storage',
    'db/migrations' => __DIR__ . '/../db/migrations',
    'tests' => __DIR__ . '/../tests',
];

foreach ($requiredDirs as $name => $path) {
    if (is_dir($path)) {
        $success[] = "✓ Dossier '$name' présent";
    } else {
        $errors[] = "✗ Dossier '$name' manquant";
    }
}

// 8. Vérifier les migrations
echo "[8/8] Vérification des migrations...\n";
if (file_exists(__DIR__ . '/../db/migrations/v2_schema.sql')) {
    $success[] = "✓ Migration v2_schema.sql présente";

    // Compter les tables dans la migration
    $migrationContent = file_get_contents(__DIR__ . '/../db/migrations/v2_schema.sql');
    $tableCount = substr_count($migrationContent, 'CREATE TABLE IF NOT EXISTS');
    echo "   Nombre de tables à créer: $tableCount\n";
} else {
    $errors[] = "✗ Migration v2_schema.sql introuvable";
}

// Afficher les résultats
echo "\n========================================\n";
echo "RÉSULTATS\n";
echo "========================================\n\n";

if (!empty($success)) {
    echo "✅ SUCCÈS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  AVERTISSEMENTS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ ERREURS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "   $msg\n";
    }
    echo "\n";
}

// Verdict final
echo "========================================\n";
if (empty($errors)) {
    echo "✅ PHASE 1 VALIDÉE AVEC SUCCÈS!\n";
    echo "========================================\n\n";
    echo "Prochaines étapes:\n";
    echo "1. Appliquer la migration: mysql -u [user] -p [db] < db/migrations/v2_schema.sql\n";
    echo "2. Vérifier les logs dans le dossier logs/\n";
    echo "3. Configurer le fichier .env si nécessaire\n";
    echo "4. Passer à la Phase 2 (Authentification)\n";
    exit(0);
} else {
    echo "❌ PHASE 1 INCOMPLÈTE\n";
    echo "========================================\n\n";
    echo "Veuillez corriger les erreurs ci-dessus avant de continuer.\n";
    exit(1);
}
