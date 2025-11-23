# Phase 1 : Infrastructure - Statelec V2

**Date de début:** 2025-11-23
**Statut:** En cours
**Version:** 2.0.0-dev

## Objectifs de la Phase 1

Mettre en place l'infrastructure de développement nécessaire pour la V2, incluant :
- Structure de dossiers organisée
- Migrations de base de données pour nouvelles fonctionnalités
- Configuration centralisée
- Système de logging professionnel
- Préparation pour les futures phases

## Réalisations

### 1. Création de la branche `evolution`

La branche `evolution` a été créée depuis `master` pour développer la V2 sans impacter la version actuelle en production.

```bash
git checkout -b evolution
```

### 2. Nouvelle structure de dossiers

```
statelec2/
├── cache/              # Cache de l'application (avec .gitkeep)
├── config/             # Configuration centralisée
│   └── app.php         # Fichier de configuration principal
├── db/
│   └── migrations/     # Scripts de migration SQL
│       ├── v2_schema.sql
│       └── README.md
├── logs/               # Logs de l'application (avec .gitkeep)
├── storage/            # Stockage fichiers
│   └── exports/        # Exports générés
├── tests/              # Tests automatisés (Phase 11)
│   └── README.md
└── src/
    ├── bootstrap.php   # Bootstrap de l'application
    └── Service/
        └── Logger.php  # Service de logging
```

### 3. Migrations de base de données

**Fichier:** `db/migrations/v2_schema.sql`

**13 nouvelles tables créées:**

| Table | Description | Utilisation |
|-------|-------------|-------------|
| `users` | Utilisateurs multi-comptes | Phase 2 (Auth) |
| `user_preferences` | Préférences personnalisées | Phase 2 |
| `events` | Événements détectés | Phase 3-5 |
| `daily_stats` | Stats quotidiennes | Phase 3-5 |
| `hourly_stats` | Stats horaires | Phase 6 |
| `devices_detected` | Appareils détectés | Phase 4 |
| `predictions` | Prévisions | Phase 5 |
| `anomalies` | Anomalies détectées | Phase 3 |
| `base_load_history` | Historique veilles | Phase 5 |
| `export_jobs` | Jobs d'export | Phase 9 |
| `api_tokens` | Tokens API | Phase 9 |
| `sessions` | Sessions utilisateur | Phase 2 |
| `notifications` | Notifications in-app | Phase 8 |

**Compte admin par défaut:**
- Email: `admin@statelec.local`
- Mot de passe: `admin123` ⚠️ **À CHANGER EN PRODUCTION**

### 4. Configuration centralisée

**Fichier:** `config/app.php`

Configuration complète incluant :
- Paramètres généraux de l'application
- Configuration base de données
- Configuration logging (Monolog)
- Configuration cache (Redis/APCu/File)
- Configuration sécurité (sessions, CSRF, rate limiting)
- Configuration API
- Configuration alertes
- Configuration tarifs électricité
- Configuration analytics
- Configuration temps réel (SSE)
- Configuration export
- Configuration PWA
- **Feature flags** pour activation progressive des fonctionnalités

### 5. Système de logging professionnel

**Installation de Monolog:**
```bash
composer require monolog/monolog
```

**Service créé:** `src/Service/Logger.php`

**Fonctionnalités:**
- Rotation automatique des logs (30 jours par défaut)
- Niveaux de log : debug, info, notice, warning, error, critical, alert, emergency
- Méthodes spécialisées :
  - `logApiRequest()` - Log des requêtes API
  - `logApiError()` - Log des erreurs API
  - `logLogin()` - Log des tentatives de connexion
  - `logAnomaly()` - Log des anomalies détectées
  - `logAlert()` - Log des alertes envoyées
  - `logDatabaseError()` - Log des erreurs BDD
  - `logPerformance()` - Log des performances
- Format personnalisé avec contexte et métadonnées
- Support du mode console pour CLI

### 6. Bootstrap de l'application

**Fichier:** `src/bootstrap.php`

**Responsabilités:**
- Chargement de l'autoloader Composer
- Chargement des variables d'environnement (.env)
- Chargement de la configuration
- Configuration du timezone
- Gestion des erreurs selon l'environnement
- Initialisation du logger
- Configuration des sessions (prêt pour Phase 2)
- Définition des constantes de l'application
- Gestionnaire d'erreurs personnalisé
- Gestionnaire d'exceptions non capturées

### 7. Mise à jour du .gitignore

Ajouts pour la V2 :
- Exclusion des logs (mais garde .gitkeep)
- Exclusion du cache (mais garde .gitkeep)
- Exclusion du storage (mais garde .gitkeep)
- Exclusion de la couverture PHPUnit
- Exclusion des exports générés
- Conservation du .htaccess public

## Dépendances ajoutées

### Nouvelles dépendances Composer

```json
{
  "monolog/monolog": "^3.0@dev",
  "psr/log": "dev-master"
}
```

### Dépendances existantes (conservées)

- vlucas/phpdotenv
- twig/twig
- symfony/polyfill-*
- graham-campbell/result-type
- phpoption/phpoption

## Structure de configuration

### Variables d'environnement (.env)

Les variables suivantes peuvent être configurées :

```env
# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

# Base de données
DB_HOST=localhost
DB_PORT=3306
DB_NAME=statelec
DB_USER=root
DB_PASS=

# Timezone
TIMEZONE=Europe/Paris

# Logging
LOG_LEVEL=info

# Cache
CACHE_ENABLED=false
CACHE_DRIVER=file
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# API
API_KEY=your_api_key_here

# Alertes
ALERT_EMAIL_ENABLED=false
ALERT_EMAIL_FROM=noreply@statelec.local
ALERT_EMAIL_TO=admin@statelec.local
ALERT_WEBHOOK_ENABLED=false
ALERT_WEBHOOK_URL=
```

## Feature Flags

Tous les feature flags sont actuellement à `false` et seront activés progressivement :

```php
'features' => [
    'authentication' => false,      // Phase 2
    'advanced_dashboard' => false,  // Phase 3-5
    'realtime_updates' => false,    // Phase 7
    'advanced_alerts' => false,     // Phase 8
    'api_v1' => false,              // Phase 9
    'export' => false,              // Phase 9
    'pwa' => false,                 // Phase 10
    'multi_user' => false,          // Phase 2
]
```

## Instructions de déploiement

### 1. Appliquer les migrations

**Via MySQL CLI:**
```bash
mysql -u [user] -p [database] < db/migrations/v2_schema.sql
```

**Via phpMyAdmin:**
1. Ouvrir phpMyAdmin
2. Sélectionner la base de données
3. Onglet SQL
4. Copier/coller le contenu de `v2_schema.sql`
5. Exécuter

### 2. Vérifier les permissions

```bash
# Assurer que les dossiers sont accessibles en écriture
chmod 755 logs/
chmod 755 cache/
chmod 755 storage/
chmod 755 storage/exports/
```

### 3. Tester la configuration

Les tests seront disponibles en Phase 11, mais vous pouvez vérifier manuellement :

1. Vérifier que toutes les tables ont été créées :
```sql
SHOW TABLES;
```

2. Vérifier le compte admin :
```sql
SELECT email, name, role FROM users;
```

3. Tester le logging :
```php
require 'src/bootstrap.php';
\App\Service\Logger::info('Test de logging');
// Vérifier logs/app.log
```

## Prochaines étapes - Phase 2

La Phase 2 implémentera le système d'authentification complet :
- AuthController
- AuthService
- AuthMiddleware
- Pages de login/register
- Protection des routes
- Gestion des rôles (admin/user)
- Sécurité CSRF
- Rate limiting

## Notes techniques

### Compatibilité

- PHP 8.1+ requis
- MySQL 5.7+ ou MariaDB 10.3+
- Extensions PHP requises : PDO, pdo_mysql, json, mbstring

### Performances

La configuration actuelle est optimisée pour le développement. Pour la production :
- Activer le cache (`CACHE_ENABLED=true`)
- Utiliser Redis si disponible (`CACHE_DRIVER=redis`)
- Désactiver le debug (`APP_DEBUG=false`)
- Configurer l'environnement en production (`APP_ENV=production`)

### Sécurité

Points de sécurité actuels :
- Mots de passe hashés avec bcrypt (cost 10)
- Contraintes de clés étrangères avec CASCADE
- Validation des types avec strict typing
- Gestion des erreurs sécurisée
- Séparation environnement dev/prod

⚠️ **Important:** Changer le mot de passe admin par défaut dès l'installation !

## Changelog Phase 1

- [x] Création branche `evolution`
- [x] Structure de dossiers V2
- [x] Script de migration `v2_schema.sql` (13 nouvelles tables)
- [x] Configuration centralisée `config/app.php`
- [x] Installation Monolog
- [x] Service de logging `Logger.php`
- [x] Bootstrap `src/bootstrap.php`
- [x] Mise à jour `.gitignore`
- [x] Documentation migrations
- [x] Documentation Phase 1

## Auteurs

Développé avec Claude Code pour l'évolution de Statelec vers la V2.
