<?php

declare(strict_types=1);

namespace Statelec\Service;

use Statelec\Service\Database;
use PDO;
use DateTime;
use DateTimeZone;

class IndicateursService
{
    private ?PDO $pdo;
    private string $timezone;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->timezone = $_ENV['TIMEZONE'] ?? 'Europe/Paris';
    }

    /**
     * Récupère tous les indicateurs pour la période spécifiée
     */
    public function getAllIndicateurs(string $periode = 'jour'): array
    {
        if (!$this->pdo) {
            return $this->getEmptyIndicateurs();
        }

        try {
            return [
                'mesures_brutes' => $this->getMesuresElectriques($periode),
                'stats_temporelles' => $this->getStatsTemporelles($periode),
                'evenements' => $this->getEvenementsElectriques($periode),
                'gaspillage' => $this->getIndicateursGaspillage($periode),
                'cout' => $this->getIndicateursCout($periode)
            ];
        } catch (\Exception $e) {
            error_log("Erreur IndicateursService::getAllIndicateurs: " . $e->getMessage());
            return $this->getEmptyIndicateurs();
        }
    }

    /**
     * Catégorie 1: Mesures électriques brutes
     */
    private function getMesuresElectriques(string $periode): array
    {
        $dateRange = $this->getDateRange($periode);
        
        // Puissance instantanée (dernière mesure)
        $stmt = $this->pdo->query("SELECT papp, timestamp FROM consumption_data ORDER BY timestamp DESC LIMIT 1");
        $lastMeasure = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $puissanceInstantanee = $lastMeasure ? (int)$lastMeasure['papp'] : 0;
        $lastTimestamp = $lastMeasure ? $lastMeasure['timestamp'] : null;

        // Puissance max par période
        $puissanceMax = $this->getPuissanceMaxPeriodes($dateRange);

        // Énergie consommée par période
        $energieConsommee = $this->getEnergieConsommeePeriodes($dateRange);

        // Courbe temporelle (données pour graphique)
        $courbeTemporelle = $this->getCourbeTemporelle($dateRange);

        return [
            'puissance_instantanee' => $puissanceInstantanee,
            'last_timestamp' => $lastTimestamp,
            'puissance_max' => $puissanceMax,
            'energie_consommee' => $energieConsommee,
            'courbe_temporelle' => $courbeTemporelle
        ];
    }

    /**
     * Catégorie 2: Stats temporelles très robustes
     */
    private function getStatsTemporelles(string $periode): array
    {
        $dateRange = $this->getDateRange($periode);

        // Consommation nocturne moyenne (00h-06h)
        $stmt = $this->pdo->prepare("
            SELECT AVG(papp) as avg_nocturne
            FROM consumption_data
            WHERE HOUR(timestamp) >= 0 AND HOUR(timestamp) < 6
            AND timestamp >= ? AND timestamp <= ?
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        $consoNocturne = (int)($stmt->fetchColumn() ?: 0);

        // Profil moyen journalier (moyenne par heure)
        $profilJournalier = $this->getProfilJournalier($dateRange);

        // Périodes de pointe
        $periodesPointe = $this->getPeriodesPointe($profilJournalier);

        // Comparaison période N / N-1
        $comparaison = $this->getComparaisonPeriodes($periode);

        return [
            'conso_nocturne' => $consoNocturne,
            'profil_journalier' => $profilJournalier,
            'periodes_pointe' => $periodesPointe,
            'comparaison' => $comparaison
        ];
    }

    /**
     * Catégorie 3: Détection d'événements électriques vérifiables
     */
    private function getEvenementsElectriques(string $periode): array
    {
        $dateRange = $this->getDateRange($periode);
        
        // Récupérer les données brutes pour l'analyse
        // On limite aux 1000 derniers points pour ne pas surcharger si la période est longue
        $stmt = $this->pdo->prepare("
            SELECT timestamp, papp 
            FROM consumption_data 
            WHERE timestamp >= ? AND timestamp <= ?
            ORDER BY timestamp ASC
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Paramètres (à récupérer des settings plus tard)
        $seuilSaut = 500; // Watts
        $seuilChargeElevee = 2000; // Watts (ex: four, chauffe-eau)
        $dureeMinCharge = 30; // Minutes
        $seuilAnomalie = 6000; // Watts (proche disjonction ou anormal)

        $sauts = $this->detecterSautsPuissance($data, $seuilSaut);
        $charges = $this->detecterChargesElevees($data, $seuilChargeElevee, $dureeMinCharge);
        $anomalies = $this->detecterAnomalies($data, $seuilAnomalie);

        return [
            'sauts_puissance' => $sauts,
            'anomalies' => $anomalies,
            'charges_elevees' => $charges
        ];
    }

    private function detecterSautsPuissance(array $data, int $seuil): array
    {
        $events = [];
        $count = count($data);
        
        for ($i = 1; $i < $count; $i++) {
            $prev = (int)$data[$i-1]['papp'];
            $curr = (int)$data[$i]['papp'];
            $delta = $curr - $prev;
            
            if (abs($delta) >= $seuil) {
                $events[] = [
                    'timestamp' => $data[$i]['timestamp'],
                    'type' => $delta > 0 ? 'montée' : 'descente',
                    'delta' => $delta,
                    'valeur_avant' => $prev,
                    'valeur_apres' => $curr
                ];
            }
        }
        
        // Trier par timestamp décroissant
        usort($events, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
        
        return array_slice($events, 0, 20);
    }

    private function detecterChargesElevees(array $data, int $seuilWatts, int $dureeMinMinutes): array
    {
        $charges = [];
        $currentStart = null;
        $count = count($data);
        
        for ($i = 0; $i < $count; $i++) {
            $papp = (int)$data[$i]['papp'];
            $timestamp = $data[$i]['timestamp'];
            
            if ($papp >= $seuilWatts) {
                if ($currentStart === null) {
                    $currentStart = $timestamp;
                }
            } else {
                if ($currentStart !== null) {
                    // Fin d'une période de charge
                    $this->enregistrerCharge($charges, $currentStart, $data[$i-1]['timestamp'], $dureeMinMinutes, $seuilWatts);
                    $currentStart = null;
                }
            }
        }
        
        // Vérifier si une charge est en cours à la fin des données
        if ($currentStart !== null) {
            $this->enregistrerCharge($charges, $currentStart, $data[$count-1]['timestamp'], $dureeMinMinutes, $seuilWatts);
        }
        
        return array_reverse($charges); // Les plus récents en premier
    }

    private function enregistrerCharge(array &$charges, string $start, string $end, int $minDuration, int $avgPower): void
    {
        try {
            $startTime = new \DateTime($start);
            $endTime = new \DateTime($end);
            $durationSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
            $durationMinutes = round($durationSeconds / 60);

            if ($durationMinutes >= $minDuration) {
                $charges[] = [
                    'start' => $start,
                    'end' => $end,
                    'duration' => $durationMinutes,
                    'avg_power' => $avgPower // Simplifié, on pourrait calculer la vraie moyenne
                ];
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs de date
        }
    }

    private function detecterAnomalies(array $data, int $seuilMax): array
    {
        $anomalies = [];
        
        foreach ($data as $point) {
            $papp = (int)$point['papp'];
            
            // 1. Détection de pics très élevés (proche abonnement)
            if ($papp >= $seuilMax) {
                $anomalies[] = [
                    'timestamp' => $point['timestamp'],
                    'type' => 'pic_critique',
                    'valeur' => $papp,
                    'message' => "Pic critique détecté (> {$seuilMax}W)"
                ];
            }
            
            // 2. Détection consommation anormale la nuit (ex: > 1000W entre 2h et 4h)
            // Nécessite de parser l'heure, un peu coûteux ici, on le fait simplement
            try {
                $date = new \DateTime($point['timestamp']);
                $hour = (int)$date->format('H');
                
                if ($hour >= 2 && $hour < 5 && $papp > 1000) {
                     $anomalies[] = [
                        'timestamp' => $point['timestamp'],
                        'type' => 'conso_nuit',
                        'valeur' => $papp,
                        'message' => "Consommation nocturne anormale (> 1000W)"
                    ];
                }
            } catch (\Exception $e) {}
        }
        
        // Trier et limiter
        usort($anomalies, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
        return array_slice($anomalies, 0, 10);
    }

    /**
     * Catégorie 4: Indicateurs de gaspillage raisonnablement fiables
     */
    private function getIndicateursGaspillage(string $periode): array
    {
        $dateRange = $this->getDateRange($periode);

        // Veille globale (minimum de la moyenne horaire, en ignorant les zéros)
        $profilJournalier = $this->getProfilJournalier($dateRange);
        $puissances = array_column($profilJournalier, 'puissance');
        $puissancesNonNulles = array_filter($puissances, fn($p) => $p > 0);
        $veilleGlobale = !empty($puissancesNonNulles) ? min($puissancesNonNulles) : 0;

        // Base nocturne (02h-05h)
        $stmt = $this->pdo->prepare("
            SELECT AVG(papp) as base_nocturne
            FROM consumption_data
            WHERE HOUR(timestamp) >= 2 AND HOUR(timestamp) < 5
            AND timestamp >= ? AND timestamp <= ?
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        $baseNocturne = (int)($stmt->fetchColumn() ?: 0);

        // Écart semaine/week-end (toujours sur les 30 derniers jours pour être significatif)
        $dateRange30Jours = $this->getDateRange('mois'); // Utilise le mois en cours, ou on peut forcer 30 jours glissants
        
        // Pour être plus précis, prenons 30 jours glissants
        $timezone = new DateTimeZone($this->timezone);
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $timezone);
        $start30 = (clone $now)->modify('-30 days')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
        $end30 = (clone $now)->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
        
        $ecartWeekend = $this->getEcartWeekend(['start' => $start30, 'end' => $end30]);

        return [
            'veille_globale' => (int)$veilleGlobale,
            'base_nocturne' => $baseNocturne,
            'ecart_weekend' => $ecartWeekend,
            'charges_stables' => [] // TODO: Implémenter détection charges stables
        ];
    }

    /**
     * Catégorie 5: Coût
     */
    private function getIndicateursCout(string $periode): array
    {
        // Récupérer les tarifs depuis les settings
        $tarifs = $this->getTarifs();
        
        // Coût par période
        $couts = $this->getCoutsPeriodes($tarifs);

        // Coût projeté fin de mois
        $coutProjete = $this->getCoutProjete($tarifs);

        // Courbe coût vs jours du mois
        $courbeCout = $this->getCourbeCoutMensuel($tarifs);

        return [
            'couts' => $couts,
            'cout_projete' => $coutProjete,
            'courbe_cout' => $courbeCout,
            'tarifs' => $tarifs
        ];
    }

    // ========== Méthodes utilitaires ==========

    private function getDateRange(string $periode): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $now = new DateTime('now', $timezone);
        
        switch ($periode) {
            case 'jour':
                $start = (clone $now)->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
                break;
            case 'semaine':
                $start = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
                $end = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);
                break;
            case 'mois':
                $start = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
                $end = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
                break;
            default:
                $start = (clone $now)->setTime(0, 0, 0);
                $end = (clone $now)->setTime(23, 59, 59);
        }

        // Conversion en UTC pour la requête DB car les données sont stockées en UTC (avec Z)
        $start->setTimezone(new DateTimeZone('UTC'));
        $end->setTimezone(new DateTimeZone('UTC'));

        // Format ISO 8601 avec T et Z
        return [
            'start' => $start->format('Y-m-d\TH:i:s\Z'),
            'end' => $end->format('Y-m-d\TH:i:s\Z')
        ];
    }

    private function getPuissanceMaxPeriodes(array $dateRange): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $timezone);
        
        $periodes = [
            'jour' => [
                'start' => (clone $now)->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z')
            ],
            'semaine' => [
                'start' => (clone $now)->modify('monday this week')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->modify('sunday this week')->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z')
            ],
            'mois' => [
                'start' => (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->modify('last day of this month')->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z')
            ]
        ];

        $result = [];
        foreach ($periodes as $key => $range) {
            $stmt = $this->pdo->prepare("SELECT MAX(papp) FROM consumption_data WHERE timestamp >= ? AND timestamp <= ?");
            $stmt->execute([$range['start'], $range['end']]);
            $result[$key] = (int)($stmt->fetchColumn() ?: 0);
        }

        return $result;
    }

    private function getEnergieConsommeePeriodes(array $dateRange): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $timezone);
        
        $periodes = [
            'jour' => [
                'start' => (clone $now)->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z')
            ],
            'semaine' => [
                'start' => (clone $now)->modify('monday this week')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->modify('sunday this week')->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z')
            ],
            'mois' => [
                'start' => (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->modify('last day of this month')->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z')
            ]
        ];

        $result = [];
        foreach ($periodes as $key => $range) {
            $energie = $this->calculerEnergie($range['start'], $range['end']);
            $result[$key] = $energie;
        }

        return $result;
    }

    private function calculerEnergie(string $start, string $end): float
    {
        $stmt = $this->pdo->prepare("
            SELECT hchc, hchp FROM consumption_data 
            WHERE timestamp >= ? AND timestamp <= ?
            ORDER BY timestamp ASC
            LIMIT 1
        ");
        $stmt->execute([$start, $end]);
        $first = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("
            SELECT hchc, hchp FROM consumption_data 
            WHERE timestamp >= ? AND timestamp <= ?
            ORDER BY timestamp DESC
            LIMIT 1
        ");
        $stmt->execute([$start, $end]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$first || !$last) {
            return 0.0;
        }

        $consoHC = (float)$last['hchc'] - (float)$first['hchc'];
        $consoHP = (float)$last['hchp'] - (float)$first['hchp'];

        return round($consoHC + $consoHP, 2); // Les index sont déjà en kWh
    }

    private function getCourbeTemporelle(array $dateRange): array
    {
        $start = new DateTime($dateRange['start']);
        $end = new DateTime($dateRange['end']);
        $diff = $start->diff($end);
        $hours = $diff->h + ($diff->days * 24);

        // Déterminer l'intervalle d'échantillonnage en minutes
        $intervalMinutes = 1; // Par défaut : toutes les données
        if ($hours > 24 * 7) { // Plus d'une semaine
            $intervalMinutes = 60; // 1 point par heure
        } elseif ($hours > 24) { // Plus d'un jour
            $intervalMinutes = 15; // 1 point toutes les 15 min
        } elseif ($hours > 6) { // Plus de 6 heures
            $intervalMinutes = 5; // 1 point toutes les 5 min
        }

        // Si l'intervalle est 1 minute, on prend tout
        if ($intervalMinutes === 1) {
            $stmt = $this->pdo->prepare("
                SELECT timestamp, papp 
                FROM consumption_data 
                WHERE timestamp >= ? AND timestamp <= ?
                ORDER BY timestamp ASC
            ");
            $stmt->execute([$dateRange['start'], $dateRange['end']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Sinon, on échantillonne en SQL pour les performances
        // Note: Cette requête est optimisée pour MySQL
        $stmt = $this->pdo->prepare("
            SELECT 
                FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(timestamp)/(:interval_1 * 60)) * (:interval_2 * 60)) as time_slice,
                AVG(papp) as papp
            FROM consumption_data 
            WHERE timestamp >= :start AND timestamp <= :end
            GROUP BY time_slice
            ORDER BY time_slice ASC
        ");
        
        $stmt->bindValue(':interval_1', $intervalMinutes, PDO::PARAM_INT);
        $stmt->bindValue(':interval_2', $intervalMinutes, PDO::PARAM_INT);
        $stmt->bindValue(':start', $dateRange['start']);
        $stmt->bindValue(':end', $dateRange['end']);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater pour correspondre à la structure attendue (timestamp, papp)
        return array_map(function($row) {
            return [
                'timestamp' => $row['time_slice'],
                'papp' => (int)$row['papp']
            ];
        }, $results);
    }

    private function getProfilJournalier(array $dateRange): array
    {
        // On groupe par heure complète UTC pour pouvoir convertir ensuite
        // SUBSTRING(timestamp, 1, 13) donne 'YYYY-MM-DDTHH'
        $stmt = $this->pdo->prepare("
            SELECT 
                SUBSTRING(timestamp, 1, 13) as heure_utc,
                AVG(papp) as puissance,
                MAX(papp) as puissance_max,
                MIN(papp) as puissance_min
            FROM consumption_data
            WHERE timestamp >= ? AND timestamp <= ?
            GROUP BY heure_utc
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $profil = array_fill(0, 24, ['count' => 0, 'puissance' => 0, 'max' => 0, 'min' => 99999]);
        $timezone = new DateTimeZone($this->timezone);

        foreach ($rows as $row) {
            // Reconstruire la date UTC complète : YYYY-MM-DDTHH + :00:00Z
            // Attention au format exact retourné par SUBSTRING
            $dateStr = $row['heure_utc'] . ':00:00Z';
            try {
                $utcDate = new DateTime($dateStr); // Le Z à la fin indique UTC
                $utcDate->setTimezone($timezone); // Convertir en local
                $h = (int)$utcDate->format('G'); // Heure locale 0-23

                $profil[$h]['count']++;
                $profil[$h]['puissance'] += $row['puissance'];
                $profil[$h]['max'] = max($profil[$h]['max'], $row['puissance_max']);
                $profil[$h]['min'] = min($profil[$h]['min'], $row['puissance_min']);
            } catch (\Exception $e) {
                continue;
            }
        }

        // Calculer les moyennes finales
        $final = [];
        for ($h = 0; $h < 24; $h++) {
            $count = $profil[$h]['count'];
            $final[] = [
                'heure' => $h,
                'puissance' => $count > 0 ? round($profil[$h]['puissance'] / $count) : 0,
                'puissance_max' => $count > 0 ? $profil[$h]['max'] : 0,
                'puissance_min' => $count > 0 ? $profil[$h]['min'] : 0
            ];
        }

        return $final;
    }

    private function getPeriodesPointe(array $profilJournalier): array
    {
        if (empty($profilJournalier)) {
            return ['pointe' => [], 'creuse' => []];
        }

        $sorted = $profilJournalier;
        usort($sorted, fn($a, $b) => $b['puissance'] <=> $a['puissance']);

        $pointe = array_slice($sorted, 0, 3);
        $creuse = array_slice($sorted, -3);

        return [
            'pointe' => array_map(fn($h) => $h['heure'], $pointe),
            'creuse' => array_map(fn($h) => $h['heure'], $creuse)
        ];
    }

    private function getComparaisonPeriodes(string $periode): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $timezone);
        
        // Définir les périodes actuelle et précédente
        switch ($periode) {
            case 'jour':
                $currentStart = (clone $now)->setTime(0, 0, 0);
                $currentEnd = (clone $now)->setTime(23, 59, 59);
                $previousStart = (clone $now)->modify('-1 day')->setTime(0, 0, 0);
                $previousEnd = (clone $now)->modify('-1 day')->setTime(23, 59, 59);
                break;
            case 'semaine':
                $currentStart = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
                $currentEnd = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);
                $previousStart = (clone $now)->modify('monday last week')->setTime(0, 0, 0);
                $previousEnd = (clone $now)->modify('sunday last week')->setTime(23, 59, 59);
                break;
            case 'mois':
                $currentStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
                $currentEnd = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
                $previousStart = (clone $now)->modify('first day of last month')->setTime(0, 0, 0);
                $previousEnd = (clone $now)->modify('last day of last month')->setTime(23, 59, 59);
                break;
            default:
                return [
                    'variation' => 0,
                    'periode_actuelle' => 0,
                    'periode_precedente' => 0
                ];
        }

        // Convertir en UTC pour les requêtes
        $currentStart->setTimezone($utc);
        $currentEnd->setTimezone($utc);
        $previousStart->setTimezone($utc);
        $previousEnd->setTimezone($utc);

        // Calculer l'énergie pour la période actuelle
        $energieActuelle = $this->calculerEnergie(
            $currentStart->format('Y-m-d\TH:i:s\Z'),
            $currentEnd->format('Y-m-d\TH:i:s\Z')
        );

        // Calculer l'énergie pour la période précédente
        $energiePrecedente = $this->calculerEnergie(
            $previousStart->format('Y-m-d\TH:i:s\Z'),
            $previousEnd->format('Y-m-d\TH:i:s\Z')
        );

        // Calculer la variation en pourcentage
        $variation = 0;
        if ($energiePrecedente > 0) {
            $variation = (($energieActuelle - $energiePrecedente) / $energiePrecedente) * 100;
        }

        return [
            'variation' => round($variation, 1),
            'periode_actuelle' => $energieActuelle,
            'periode_precedente' => $energiePrecedente
        ];
    }

    private function getEcartWeekend(array $dateRange): array
    {
        // Moyenne semaine (lundi-vendredi)
        $stmt = $this->pdo->prepare("
            SELECT AVG(papp) as avg_semaine
            FROM consumption_data
            WHERE WEEKDAY(timestamp) IN (0,1,2,3,4)
            AND timestamp >= ? AND timestamp <= ?
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        $avgSemaine = (int)($stmt->fetchColumn() ?: 0);

        // Moyenne week-end (samedi-dimanche)
        $stmt = $this->pdo->prepare("
            SELECT AVG(papp) as avg_weekend
            FROM consumption_data
            WHERE WEEKDAY(timestamp) IN (5,6)
            AND timestamp >= ? AND timestamp <= ?
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        $avgWeekend = (int)($stmt->fetchColumn() ?: 0);

        $ecart = null;
        if ($avgSemaine > 0 && $avgWeekend > 0) {
            $ecart = (($avgWeekend - $avgSemaine) / $avgSemaine) * 100;
            $ecart = round($ecart, 1);
        }

        return [
            'avg_semaine' => $avgSemaine,
            'avg_weekend' => $avgWeekend,
            'ecart_pourcent' => $ecart
        ];
    }

    private function getTarifs(): array
    {
        $stmt = $this->pdo->query("SELECT `key`, value FROM settings WHERE `key` IN ('prixHC', 'prixHP', 'budgetMensuel', 'subscription_price')");
        $rawSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings[$key] = json_decode($value, true);
        }

        return [
            'hc' => isset($settings['prixHC']) ? (float)$settings['prixHC'] : 0.1821,
            'hp' => isset($settings['prixHP']) ? (float)$settings['prixHP'] : 0.2460,
            'budget_mensuel' => isset($settings['budgetMensuel']) ? (float)$settings['budgetMensuel'] : 50.0,
            'abonnement_mensuel' => isset($settings['subscription_price']) ? (float)$settings['subscription_price'] : 12.0
        ];
    }

    private function getCoutsPeriodes(array $tarifs): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $timezone);
        
        $periodes = [
            'jour' => [
                'start' => (clone $now)->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'jours' => 1
            ],
            'semaine' => [
                'start' => (clone $now)->modify('monday this week')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->modify('sunday this week')->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'jours' => 7
            ],
            'mois' => [
                'start' => (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end' => (clone $now)->modify('last day of this month')->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'jours' => (int)$now->format('t') // Nombre de jours dans le mois
            ]
        ];

        $result = [];
        $joursDansMois = (int)$now->format('t');
        $abonnementJournalier = $tarifs['abonnement_mensuel'] / $joursDansMois; // Proratisation précise
        
        foreach ($periodes as $key => $range) {
            $coutConsommation = $this->calculerCout($range['start'], $range['end'], $tarifs);
            $coutAbonnement = $abonnementJournalier * $range['jours'];
            $result[$key] = round($coutConsommation + $coutAbonnement, 2);
        }

        return $result;
    }

    private function calculerCout(string $start, string $end, array $tarifs): float
    {
        // Récupérer les index de début
        $stmt = $this->pdo->prepare("
            SELECT hchc, hchp FROM consumption_data 
            WHERE timestamp >= ? AND timestamp <= ?
            ORDER BY timestamp ASC
            LIMIT 1
        ");
        $stmt->execute([$start, $end]);
        $first = $stmt->fetch(PDO::FETCH_ASSOC);

        // Récupérer les index de fin
        $stmt = $this->pdo->prepare("
            SELECT hchc, hchp FROM consumption_data 
            WHERE timestamp >= ? AND timestamp <= ?
            ORDER BY timestamp DESC
            LIMIT 1
        ");
        $stmt->execute([$start, $end]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$first || !$last) {
            return 0.0;
        }

        // Calculer la consommation réelle HC et HP
        $energieHC = (float)$last['hchc'] - (float)$first['hchc'];
        $energieHP = (float)$last['hchp'] - (float)$first['hchp'];

        // Calculer le coût avec les tarifs réels
        $cout = ($energieHC * $tarifs['hc']) + ($energieHP * $tarifs['hp']);
        return round($cout, 2);
    }

    private function getCoutProjete(array $tarifs): float
    {
        $timezone = new DateTimeZone($this->timezone);
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $timezone);
        $jourActuel = (int)$now->format('d');
        $joursTotal = (int)$now->format('t');

        $start = (clone $now)->modify('first day of this month')->setTime(0, 0, 0)->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
        $end = $now->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');

        $coutActuel = $this->calculerCout($start, $end, $tarifs);
        
        if ($jourActuel === 0) {
            return 0.0;
        }

        $coutProjeteConsommation = ($coutActuel / $jourActuel) * $joursTotal;
        
        // Ajouter l'abonnement mensuel complet
        $coutAbonnement = isset($tarifs['abonnement_mensuel']) ? $tarifs['abonnement_mensuel'] : 0.0;
        
        return round($coutProjeteConsommation + $coutAbonnement, 2);
    }

    private function getCourbeCoutMensuel(array $tarifs): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $timezone);
        
        $firstDay = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
        $currentDay = (int)$now->format('d');
        
        $courbe = [];
        $coutCumule = 0;
        $joursDansMois = (int)$now->format('t');
        $abonnementJournalier = $tarifs['abonnement_mensuel'] / $joursDansMois;
        
        for ($day = 1; $day <= $currentDay; $day++) {
            $dayStart = (clone $firstDay)->modify('+' . ($day - 1) . ' days')->setTime(0, 0, 0);
            $dayEnd = (clone $dayStart)->setTime(23, 59, 59);
            
            // Convertir en UTC
            $dayStart->setTimezone($utc);
            $dayEnd->setTimezone($utc);
            
            // Calculer le coût du jour (consommation + abonnement)
            $coutConsoJour = $this->calculerCout(
                $dayStart->format('Y-m-d\TH:i:s\Z'),
                $dayEnd->format('Y-m-d\TH:i:s\Z'),
                $tarifs
            );
            
            $coutJour = $coutConsoJour + $abonnementJournalier;
            $coutCumule += $coutJour;
            
            $courbe[] = [
                'jour' => $day,
                'cout_jour' => round($coutJour, 2),
                'cout_cumule' => round($coutCumule, 2)
            ];
        }
        
        return $courbe;
    }

    private function getEmptyIndicateurs(): array
    {
        return [
            'mesures_brutes' => [
                'puissance_instantanee' => 0,
                'last_timestamp' => null,
                'puissance_max' => ['jour' => 0, 'semaine' => 0, 'mois' => 0],
                'energie_consommee' => ['jour' => 0, 'semaine' => 0, 'mois' => 0],
                'courbe_temporelle' => []
            ],
            'stats_temporelles' => [
                'conso_nocturne' => 0,
                'profil_journalier' => [],
                'periodes_pointe' => ['pointe' => [], 'creuse' => []],
                'comparaison' => ['variation' => 0, 'periode_actuelle' => 0, 'periode_precedente' => 0]
            ],
            'evenements' => [
                'sauts_puissance' => [],
                'anomalies' => [],
                'charges_elevees' => []
            ],
            'gaspillage' => [
                'veille_globale' => 0,
                'base_nocturne' => 0,
                'ecart_weekend' => ['avg_semaine' => 0, 'avg_weekend' => 0, 'ecart_pourcent' => 0],
                'charges_stables' => []
            ],
            'cout' => [
                'couts' => ['jour' => 0, 'semaine' => 0, 'mois' => 0],
                'cout_projete' => 0,
                'courbe_cout' => [],
                'tarifs' => ['hc' => 0.1821, 'hp' => 0.2460, 'budget_mensuel' => 50.0]
            ]
        ];
    }
}
