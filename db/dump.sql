-- --------------------------------------------------------
-- Hôte :                        mysql-orwell.alwaysdata.net
-- Version du serveur:           10.11.14-MariaDB - MariaDB Server
-- SE du serveur:                Linux
-- HeidiSQL Version:             10.2.0.5599
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Listage de la structure de la table orwell_monitor. alerts_log
CREATE TABLE IF NOT EXISTS `alerts_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL COMMENT 'Type d''alerte (power, daily)',
  `severity` enum('high','critical') NOT NULL COMMENT 'Sévérité',
  `title` varchar(255) NOT NULL COMMENT 'Titre de l''alerte',
  `message` text NOT NULL COMMENT 'Message détaillé',
  `value` decimal(10,2) DEFAULT NULL COMMENT 'Valeur qui a déclenché l''alerte',
  `threshold` decimal(10,2) DEFAULT NULL COMMENT 'Seuil dépassé',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Date de création',
  PRIMARY KEY (`id`),
  KEY `idx_type_created` (`type`,`created_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Historique des alertes envoyées';

-- Les données exportées n'étaient pas sélectionnées.

-- Listage de la structure de la table orwell_monitor. consumption_data
CREATE TABLE IF NOT EXISTS `consumption_data` (
  `timestamp` varchar(255) NOT NULL,
  `papp` int(11) DEFAULT NULL,
  `hchc` decimal(10,2) DEFAULT NULL,
  `hchp` decimal(10,2) DEFAULT NULL,
  `ptec` int(11) DEFAULT NULL,
  PRIMARY KEY (`timestamp`),
  KEY `idx_consumption_papp` (`papp`),
  KEY `idx_consumption_hchc` (`hchc`),
  KEY `idx_consumption_hchp` (`hchp`),
  KEY `idx_consumption_ptec` (`ptec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les données exportées n'étaient pas sélectionnées.

-- Listage de la structure de la table orwell_monitor. settings
CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les données exportées n'étaient pas sélectionnées.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
