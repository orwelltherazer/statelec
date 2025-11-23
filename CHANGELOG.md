# Changelog - Statelec V2

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [Non publié]

### [2.0.0] - 2025-11-23

#### Phase 1 - Infrastructure

##### Ajouté

- **Branche `evolution`** créée pour le développement de la V2
- **Structure de dossiers** organisée pour la V2 :
  - `config/` - Configuration centralisée
  - `db/migrations/` - Scripts de migration SQL
  - `logs/` - Logs de l'application
  - `cache/` - Cache applicatif
  - `storage/` - Stockage de fichiers
  - `tests/` - Tests automatisés
- **13 nouvelles tables** dans la base de données :
  - `users` - Gestion multi-utilisateurs
  - `user_preferences` - Préférences utilisateur
  - `events` - Événements détectés
  - `daily_stats` - Statistiques quotidiennes
  - `hourly_stats` - Statistiques horaires
  - `devices_detected` - Appareils détectés
  - `predictions` - Prévisions
  - `anomalies` - Anomalies détectées
  - `base_load_history` - Historique charge de base
  - `export_jobs` - Jobs d'export
  - `api_tokens` - Tokens API
  - `sessions` - Sessions utilisateur
  - `notifications` - Notifications in-app
- **Configuration centralisée** dans `config/app.php` :
  - Configuration application
  - Configuration base de données
  - Configuration logging
  - Configuration cache (Redis/APCu/File)
  - Configuration sécurité (CSRF, rate limiting)
  - Configuration API
  - Configuration alertes
  - Configuration analytics
  - Feature flags pour activation progressive
- **Système de logging professionnel** avec Monolog :
  - Service `Logger.php` avec méthodes spécialisées
  - Rotation automatique des logs (30 jours)
  - Support multi-niveaux (debug, info, warning, error, etc.)
  - Méthodes spécialisées pour API, auth, anomalies, performances
- **Bootstrap** de l'application (`src/bootstrap.php`) :
  - Chargement autoloader Composer
  - Chargement variables d'environnement
  - Configuration timezone
  - Gestion des erreurs selon environnement
  - Initialisation du logger
  - Gestionnaire d'erreurs et exceptions personnalisés
- **Script de validation** Phase 1 (`tests/phase1_validation.php`)
- **Documentation complète** :
  - `docs/PHASE1_INFRASTRUCTURE.md` - Documentation Phase 1
  - `db/migrations/README.md` - Guide des migrations
  - `tests/README.md` - Guide des tests
  - `CHANGELOG.md` - Ce fichier

##### Modifié

- `.gitignore` mis à jour pour V2 (exclusion logs, cache, storage, coverage)
- `composer.json` - Ajout de Monolog
- `composer.lock` - Verrouillage dépendances

##### Déplacé

- `db/dump.sql` → `docs/Structure_bdd.sql` (documentation)

##### Supprimé

- `TODO.md` (ancien fichier, remplacé par documentation structurée)

##### Dépendances

- **Ajoutées:**
  - `monolog/monolog` ^3.0@dev
  - `psr/log` dev-master

- **Existantes (conservées):**
  - vlucas/phpdotenv
  - twig/twig
  - symfony/polyfill-*
  - graham-campbell/result-type
  - phpoption/phpoption

##### Notes de sécurité

⚠️ **IMPORTANT:** Le compte admin par défaut créé par la migration utilise le mot de passe `admin123`.
Ce mot de passe DOIT être changé immédiatement après l'installation en production.

Email: `admin@statelec.local`
Mot de passe: `admin123`

##### Compatibilité

- PHP 8.1+ requis
- MySQL 5.7+ ou MariaDB 10.3+
- Extensions PHP: PDO, pdo_mysql, json, mbstring

---

## [1.x] - Versions antérieures

### [1.0.0] - Version initiale

Version initiale de Statelec avec les fonctionnalités de base :
- Dashboard de monitoring
- Historique de consommation
- Calcul des coûts
- Système d'alertes basique
- Analyse de consommation
- Paramètres configurables
- Diagnostic système
- API de réception données TIC

---

## Prochaines phases

### Phase 2 - Authentification & Sécurité (À venir)
- Système d'authentification complet
- Gestion multi-utilisateurs
- Protection CSRF
- Rate limiting
- Sécurité renforcée

### Phase 3-5 - Dashboard Ultime (À venir)
- Résumé quotidien intelligent
- Détection automatique d'anomalies
- Timeline intelligente
- Reconnaissance d'appareils (heuristiques)
- Insights IA et prévisions
- Graphiques avancés

### Phase 6 - Pages améliorées (À venir)
- Historique enrichi avec comparaisons
- Analyse avancée (veilles, pics, comportements)
- Coûts avec simulations tarifaires

### Phase 7 - Temps réel & Performance (À venir)
- Server-Sent Events (SSE)
- Système de cache
- Optimisations BDD
- PWA preparation

### Phase 8 - Alertes avancées (À venir)
- Moteur d'alertes configurable
- Notifications multi-canaux
- Historique et gestion

### Phase 9 - API & Export (À venir)
- API REST complète v1
- Documentation OpenAPI
- Export CSV/Excel/PDF/JSON
- Intégrations tierces

### Phase 10 - PWA & Mobile (À venir)
- Progressive Web App
- UI/UX mobile optimisée
- Dashboard personnalisable
- Mode offline

### Phase 11 - Tests & CI/CD (À venir)
- Tests automatisés (PHPUnit)
- Documentation technique complète
- Pipeline CI/CD
- Monitoring production
