-- --------------------------------------------------------
-- Migration V2 - Statelec Evolution
-- Date: 2025-11-23
-- Description: Nouvelles tables pour fonctionnalités avancées
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- --------------------------------------------------------
-- Table: users
-- Description: Gestion multi-utilisateurs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL COMMENT 'Email unique',
  `password` varchar(255) NOT NULL COMMENT 'Hash du mot de passe',
  `name` varchar(100) NOT NULL COMMENT 'Nom complet',
  `role` enum('admin','user') NOT NULL DEFAULT 'user' COMMENT 'Rôle utilisateur',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Compte actif',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Email vérifié',
  `last_login` datetime DEFAULT NULL COMMENT 'Dernière connexion',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Utilisateurs de l''application';

-- --------------------------------------------------------
-- Table: user_preferences
-- Description: Préférences utilisateur personnalisées
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'ID utilisateur',
  `preference_key` varchar(100) NOT NULL COMMENT 'Clé de préférence',
  `preference_value` text DEFAULT NULL COMMENT 'Valeur JSON',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_key` (`user_id`,`preference_key`),
  CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Préférences utilisateur';

-- --------------------------------------------------------
-- Table: events
-- Description: Événements détectés automatiquement
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL COMMENT 'Type événement (peak, appliance_start, appliance_stop, etc.)',
  `timestamp` datetime NOT NULL COMMENT 'Date/heure événement',
  `power_w` int(11) DEFAULT NULL COMMENT 'Puissance associée',
  `duration_seconds` int(11) DEFAULT NULL COMMENT 'Durée en secondes',
  `confidence` decimal(5,2) DEFAULT NULL COMMENT 'Niveau de confiance (0-100)',
  `metadata` text DEFAULT NULL COMMENT 'Métadonnées JSON',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Événements détectés automatiquement';

-- --------------------------------------------------------
-- Table: daily_stats
-- Description: Statistiques quotidiennes agrégées
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL COMMENT 'Date du jour',
  `conso_hp_kwh` decimal(10,3) DEFAULT NULL COMMENT 'Consommation HP en kWh',
  `conso_hc_kwh` decimal(10,3) DEFAULT NULL COMMENT 'Consommation HC en kWh',
  `conso_total_kwh` decimal(10,3) DEFAULT NULL COMMENT 'Consommation totale en kWh',
  `cout_estime_eur` decimal(10,2) DEFAULT NULL COMMENT 'Coût estimé en €',
  `pic_w` int(11) DEFAULT NULL COMMENT 'Puissance maximale en W',
  `creux_w` int(11) DEFAULT NULL COMMENT 'Puissance minimale en W',
  `moyenne_w` int(11) DEFAULT NULL COMMENT 'Puissance moyenne en W',
  `base_load_w` int(11) DEFAULT NULL COMMENT 'Charge de base (veilles) en W',
  `duree_pics_seconds` int(11) DEFAULT NULL COMMENT 'Durée cumulée des pics',
  `nb_events` int(11) DEFAULT 0 COMMENT 'Nombre événements détectés',
  `score_energie` int(11) DEFAULT NULL COMMENT 'Score énergie 0-100',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date` (`date`),
  KEY `idx_conso_total` (`conso_total_kwh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Statistiques quotidiennes agrégées';

-- --------------------------------------------------------
-- Table: hourly_stats
-- Description: Statistiques horaires pour analyses fines
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hourly_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL COMMENT 'Date/heure (début de l''heure)',
  `conso_kwh` decimal(10,3) DEFAULT NULL COMMENT 'Consommation horaire en kWh',
  `moyenne_w` int(11) DEFAULT NULL COMMENT 'Puissance moyenne en W',
  `pic_w` int(11) DEFAULT NULL COMMENT 'Puissance max en W',
  `creux_w` int(11) DEFAULT NULL COMMENT 'Puissance min en W',
  `tarif_type` tinyint(1) DEFAULT NULL COMMENT 'Type tarif dominant (0=HC, 1=HP)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_timestamp` (`timestamp`),
  KEY `idx_tarif_type` (`tarif_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Statistiques horaires';

-- --------------------------------------------------------
-- Table: devices_detected
-- Description: Appareils détectés par heuristiques
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devices_detected` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_type` varchar(50) NOT NULL COMMENT 'Type appareil (heating, water_heater, fridge, etc.)',
  `device_name` varchar(100) DEFAULT NULL COMMENT 'Nom personnalisé',
  `power_signature_w` int(11) DEFAULT NULL COMMENT 'Signature puissance typique',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Appareil actif',
  `last_detected` datetime DEFAULT NULL COMMENT 'Dernière détection',
  `total_detections` int(11) DEFAULT 0 COMMENT 'Nombre total de détections',
  `metadata` text DEFAULT NULL COMMENT 'Métadonnées JSON',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_device_type` (`device_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Appareils détectés par heuristiques';

-- --------------------------------------------------------
-- Table: predictions
-- Description: Prévisions calculées
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `predictions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prediction_type` varchar(50) NOT NULL COMMENT 'Type prévision (daily, monthly, cost, etc.)',
  `target_date` date NOT NULL COMMENT 'Date cible de la prévision',
  `predicted_value` decimal(10,3) DEFAULT NULL COMMENT 'Valeur prévue',
  `confidence_interval_low` decimal(10,3) DEFAULT NULL COMMENT 'Intervalle confiance bas',
  `confidence_interval_high` decimal(10,3) DEFAULT NULL COMMENT 'Intervalle confiance haut',
  `actual_value` decimal(10,3) DEFAULT NULL COMMENT 'Valeur réelle (rempli après)',
  `accuracy` decimal(5,2) DEFAULT NULL COMMENT 'Précision % (calculé après)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prediction_type` (`prediction_type`),
  KEY `idx_target_date` (`target_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prévisions calculées';

-- --------------------------------------------------------
-- Table: anomalies
-- Description: Anomalies détectées automatiquement
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `anomalies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anomaly_type` varchar(50) NOT NULL COMMENT 'Type anomalie (standby_high, unusual_peak, etc.)',
  `detected_at` datetime NOT NULL COMMENT 'Date/heure détection',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium' COMMENT 'Sévérité',
  `title` varchar(255) NOT NULL COMMENT 'Titre',
  `description` text DEFAULT NULL COMMENT 'Description détaillée',
  `value` decimal(10,2) DEFAULT NULL COMMENT 'Valeur mesurée',
  `expected_value` decimal(10,2) DEFAULT NULL COMMENT 'Valeur attendue',
  `deviation_percent` decimal(5,2) DEFAULT NULL COMMENT 'Déviation en %',
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Anomalie résolue',
  `resolved_at` datetime DEFAULT NULL COMMENT 'Date résolution',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_anomaly_type` (`anomaly_type`),
  KEY `idx_detected_at` (`detected_at`),
  KEY `idx_severity` (`severity`),
  KEY `idx_is_resolved` (`is_resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anomalies détectées automatiquement';

-- --------------------------------------------------------
-- Table: base_load_history
-- Description: Historique de la charge de base (veilles)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `base_load_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL COMMENT 'Date/heure',
  `base_load_w` int(11) NOT NULL COMMENT 'Charge de base en W',
  `duration_minutes` int(11) DEFAULT NULL COMMENT 'Durée du niveau',
  `is_optimal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Niveau optimal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_is_optimal` (`is_optimal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique charge de base';

-- --------------------------------------------------------
-- Table: export_jobs
-- Description: Travaux d'export de données
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `export_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'ID utilisateur',
  `export_type` varchar(50) NOT NULL COMMENT 'Type export (csv, excel, pdf, json)',
  `date_range_start` date DEFAULT NULL COMMENT 'Début période',
  `date_range_end` date DEFAULT NULL COMMENT 'Fin période',
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending' COMMENT 'Statut',
  `file_path` varchar(255) DEFAULT NULL COMMENT 'Chemin fichier généré',
  `file_size_bytes` int(11) DEFAULT NULL COMMENT 'Taille fichier',
  `error_message` text DEFAULT NULL COMMENT 'Message erreur si échec',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL COMMENT 'Date fin traitement',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_export_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Travaux d''export de données';

-- --------------------------------------------------------
-- Table: api_tokens
-- Description: Tokens API pour authentification externe
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'ID utilisateur',
  `token` varchar(64) NOT NULL COMMENT 'Token API (hash)',
  `name` varchar(100) DEFAULT NULL COMMENT 'Nom du token',
  `permissions` text DEFAULT NULL COMMENT 'Permissions JSON',
  `last_used_at` datetime DEFAULT NULL COMMENT 'Dernière utilisation',
  `expires_at` datetime DEFAULT NULL COMMENT 'Date expiration',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Token actif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens API';

-- --------------------------------------------------------
-- Table: sessions
-- Description: Gestion des sessions utilisateur
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL COMMENT 'ID session',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID utilisateur',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Adresse IP',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User agent',
  `payload` text NOT NULL COMMENT 'Données session',
  `last_activity` int(11) NOT NULL COMMENT 'Dernière activité (timestamp)',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessions utilisateur';

-- --------------------------------------------------------
-- Table: notifications
-- Description: Notifications in-app pour utilisateurs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'ID utilisateur (NULL = tous)',
  `type` varchar(50) NOT NULL COMMENT 'Type notification',
  `title` varchar(255) NOT NULL COMMENT 'Titre',
  `message` text NOT NULL COMMENT 'Message',
  `link` varchar(255) DEFAULT NULL COMMENT 'Lien associé',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Lu',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Archivé',
  `read_at` datetime DEFAULT NULL COMMENT 'Date lecture',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications in-app';

-- --------------------------------------------------------
-- Insert default admin user (password: admin123 - À CHANGER EN PRODUCTION!)
-- --------------------------------------------------------
INSERT INTO `users` (`email`, `password`, `name`, `role`, `is_active`, `email_verified`)
VALUES ('admin@statelec.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'admin', 1, 1)
ON DUPLICATE KEY UPDATE `email`=`email`;

-- --------------------------------------------------------
-- Restore SQL settings
-- --------------------------------------------------------
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
