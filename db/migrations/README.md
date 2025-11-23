# Migrations de base de données - Statelec V2

## Vue d'ensemble

Ce dossier contient les scripts de migration pour faire évoluer la base de données de Statelec.

## Migrations disponibles

### v2_schema.sql - Migration initiale V2

**Date:** 2025-11-23
**Phase:** Phase 1 - Infrastructure

**Nouvelles tables créées:**
- `users` - Gestion multi-utilisateurs
- `user_preferences` - Préférences utilisateur personnalisées
- `events` - Événements détectés automatiquement
- `daily_stats` - Statistiques quotidiennes agrégées
- `hourly_stats` - Statistiques horaires pour analyses fines
- `devices_detected` - Appareils détectés par heuristiques
- `predictions` - Prévisions calculées
- `anomalies` - Anomalies détectées automatiquement
- `base_load_history` - Historique charge de base (veilles)
- `export_jobs` - Travaux d'export de données
- `api_tokens` - Tokens API pour authentification externe
- `sessions` - Gestion des sessions utilisateur
- `notifications` - Notifications in-app

**Compte admin par défaut:**
- Email: `admin@statelec.local`
- Password: `admin123` (À CHANGER EN PRODUCTION!)

## Comment appliquer une migration

### Option 1: Via phpMyAdmin
1. Ouvrir phpMyAdmin
2. Sélectionner la base de données `statelec` (ou votre nom de BDD)
3. Aller dans l'onglet "SQL"
4. Copier/coller le contenu du fichier de migration
5. Exécuter

### Option 2: Via ligne de commande MySQL
```bash
mysql -u [user] -p [database_name] < db/migrations/v2_schema.sql
```

### Option 3: Via script PHP (à venir)
Un script d'installation automatique sera créé dans les prochaines phases.

## Vérification après migration

Vérifier que toutes les tables ont été créées :
```sql
SHOW TABLES;
```

Devrait afficher:
- alerts_log (existante)
- consumption_data (existante)
- settings (existante)
- users (nouvelle)
- user_preferences (nouvelle)
- events (nouvelle)
- daily_stats (nouvelle)
- hourly_stats (nouvelle)
- devices_detected (nouvelle)
- predictions (nouvelle)
- anomalies (nouvelle)
- base_load_history (nouvelle)
- export_jobs (nouvelle)
- api_tokens (nouvelle)
- sessions (nouvelle)
- notifications (nouvelle)

## Rollback

Les migrations utilisent `CREATE TABLE IF NOT EXISTS`, donc elles sont idempotentes et peuvent être exécutées plusieurs fois sans erreur.

Pour supprimer les nouvelles tables (rollback complet):
```sql
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS api_tokens;
DROP TABLE IF EXISTS export_jobs;
DROP TABLE IF EXISTS base_load_history;
DROP TABLE IF EXISTS anomalies;
DROP TABLE IF EXISTS predictions;
DROP TABLE IF EXISTS devices_detected;
DROP TABLE IF EXISTS hourly_stats;
DROP TABLE IF EXISTS daily_stats;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS users;
```

## Notes importantes

- Les migrations sont cumulatives
- Toujours faire un backup de la base de données avant migration
- Les contraintes de clés étrangères sont définies avec CASCADE pour faciliter la suppression
- Toutes les tables utilisent `utf8mb4_unicode_ci` pour le support complet Unicode
- Les timestamps sont gérés automatiquement avec `current_timestamp()`

## Prochaines migrations

Les prochaines migrations seront ajoutées au fur et à mesure de l'évolution du projet dans ce même dossier avec une nomenclature:
- `v2.1_xxx.sql`
- `v2.2_xxx.sql`
- etc.
