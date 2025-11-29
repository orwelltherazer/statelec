# Documentation - Page Indicateurs Électriques

## Vue d'ensemble

Cette page affiche des **indicateurs électriques fiables et vérifiables** basés uniquement sur les mesures brutes du capteur, sans interprétation d'appareils. L'objectif est de fournir des données ultra-fiables pour le suivi de la consommation électrique.

## Objectifs

1. **Fiabilité maximale** : Tous les indicateurs sont basés sur des mesures directes ou des calculs mathématiques purs
2. **Pas d'interprétation** : Aucune supposition sur les appareils, uniquement des comportements électriques observables
3. **Traçabilité** : Chaque indicateur peut être vérifié par rapport aux données brutes
4. **Utilité pratique** : Aide à identifier les gaspillages sans faire de suppositions

## Architecture

### Structure MVC

```
src/
├── Controller/
│   └── IndicateursController.php    # Contrôleur principal
├── Service/
│   └── IndicateursService.php       # Logique métier et calculs
└── ...

templates/
└── pages/
    └── indicateurs.twig             # Vue de la page

public/
└── js/
    └── indicateurs.js               # Logique frontend (optionnel)
```

### Base de données

La page utilise la table `consumption_data` existante avec les colonnes :
- `timestamp` : Date et heure de la mesure
- `papp` : Puissance apparente instantanée (W)
- `hchc` : Index heures creuses (Wh)
- `hchp` : Index heures pleines (Wh)
- `ptec` : Période tarifaire en cours (1=HC, 2=HP)

## Catégories d'indicateurs

### 1. Mesures électriques brutes

**Objectif** : Afficher les mesures directes du capteur sans transformation

#### Indicateurs :

##### 1.1 Puissance instantanée (W)
- **Source** : Colonne `papp` de la dernière mesure
- **Calcul** : Lecture directe
- **Affichage** : Temps réel avec mise à jour toutes les 10 secondes
- **Fiabilité** : ★★★★★ (mesure directe)

##### 1.2 Puissance max du jour/semaine/mois
- **Source** : `MAX(papp)` sur la période
- **Calcul** : 
  ```sql
  SELECT MAX(papp) FROM consumption_data 
  WHERE timestamp >= [début_période] AND timestamp <= [fin_période]
  ```
- **Affichage** : 3 cartes séparées (jour/semaine/mois)
- **Fiabilité** : ★★★★★ (mesure directe)

##### 1.3 Énergie consommée (kWh) par jour/semaine/mois
- **Source** : Différence entre index HCHC + HCHP
- **Calcul** :
  ```
  Énergie = (HCHC_fin - HCHC_début) + (HCHP_fin - HCHP_début)
  ```
- **Affichage** : 3 cartes avec graphique d'évolution
- **Fiabilité** : ★★★★★ (index compteur)

##### 1.4 Courbe temporelle de puissance
- **Source** : Colonne `papp` avec `timestamp`
- **Calcul** : Agrégation selon le niveau de zoom
  - Vue minute : données brutes
  - Vue 10 minutes : moyenne sur 10 min
  - Vue heure : moyenne horaire
- **Affichage** : Graphique Chart.js avec zoom/pan
- **Fiabilité** : ★★★★★ (mesure directe)

---

### 2. Stats temporelles très robustes

**Objectif** : Statistiques basées sur des calculs mathématiques purs, sans interprétation

#### Indicateurs :

##### 2.1 Consommation nocturne moyenne (00h–06h)
- **Source** : Moyenne de `papp` entre 00h et 06h
- **Calcul** :
  ```sql
  SELECT AVG(papp) FROM consumption_data 
  WHERE HOUR(timestamp) >= 0 AND HOUR(timestamp) < 6
  AND timestamp >= [début_période]
  ```
- **Affichage** : Carte avec valeur en W + évolution sur 7/30 jours
- **Fiabilité** : ★★★★★ (calcul statistique pur)
- **Utilité** : Révèle la consommation de veille globale

##### 2.2 Consommation de pointe (plage horaire)
- **Source** : Identification de la plage horaire avec la puissance moyenne la plus élevée
- **Calcul** :
  ```
  Pour chaque heure H de 0 à 23:
    Calculer AVG(papp) pour tous les enregistrements où HOUR(timestamp) = H
  Retourner les 3 heures avec les moyennes les plus élevées
  ```
- **Affichage** : "Vos heures de pointe : 18h-21h (moyenne: 2450 W)"
- **Fiabilité** : ★★★★★ (calcul statistique pur)

##### 2.3 Profil moyen journalier
- **Source** : Moyenne de `papp` par heure sur la période
- **Calcul** :
  ```
  Pour H = 0 à 23:
    Moyenne[H] = AVG(papp WHERE HOUR(timestamp) = H)
  ```
- **Affichage** : Graphique en barres 24h
- **Fiabilité** : ★★★★★ (calcul statistique pur)

##### 2.4 Comparaison période N / période N-1
- **Source** : Comparaison de métriques entre deux périodes
- **Calcul** :
  ```
  Période actuelle: semaine en cours
  Période précédente: semaine dernière
  
  Variation = ((Conso_N - Conso_N-1) / Conso_N-1) * 100
  ```
- **Affichage** : "+12% vs semaine dernière" avec flèche
- **Fiabilité** : ★★★★★ (calcul mathématique)

---

### 3. Détection d'événements électriques vérifiables

**Objectif** : Détecter des variations franches du signal électrique

#### Indicateurs :

##### 3.1 Sauts de puissance (montée/descente > X W)
- **Source** : Différence entre mesures consécutives
- **Calcul** :
  ```
  Pour chaque paire de mesures (t, t+1):
    Delta = papp[t+1] - papp[t]
    Si |Delta| > seuil (ex: 500W):
      Enregistrer événement
  ```
- **Affichage** : Liste des 10 derniers événements avec timestamp et amplitude
- **Fiabilité** : ★★★★☆ (dépend du seuil configuré)
- **Configuration** : Seuil ajustable dans les paramètres

##### 3.2 Anomalies de charge (pics anormaux)
- **Source** : Détection de valeurs aberrantes par rapport à l'historique
- **Calcul** :
  ```
  Pour chaque heure H:
    Calculer moyenne_H et écart-type_H sur 30 derniers jours
    Si papp > moyenne_H + (3 * écart-type_H):
      Anomalie détectée
  ```
- **Affichage** : Alertes avec timestamp et valeur
- **Fiabilité** : ★★★★☆ (méthode statistique robuste)

##### 3.3 Consommation continue anormalement élevée
- **Source** : Détection de plateaux de puissance
- **Calcul** :
  ```
  Détecter si papp reste > seuil pendant > durée_min
  Exemple: papp > 2000W pendant > 2h
  ```
- **Affichage** : "Charge élevée stable détectée : 2150W depuis 2h15"
- **Fiabilité** : ★★★★☆ (dépend des seuils)
- **Configuration** : Seuil de puissance et durée ajustables

---

### 4. Indicateurs de gaspillage raisonnablement fiables

**Objectif** : Identifier des comportements électriques suspects sans nommer d'appareils

#### Indicateurs :

##### 4.1 Veille globale (puissance de fond)
- **Source** : Minimum de la puissance moyenne horaire
- **Calcul** :
  ```
  Pour H = 0 à 23:
    Moyenne_H = AVG(papp WHERE HOUR(timestamp) = H)
  Veille_globale = MIN(Moyenne_H)
  ```
- **Affichage** : "Consommation de fond : 85W en permanence"
- **Fiabilité** : ★★★★☆ (approximation raisonnable)
- **Utilité** : Révèle les appareils en veille permanente

##### 4.2 Base nocturne
- **Source** : Puissance moyenne entre 02h et 05h (période la plus calme)
- **Calcul** :
  ```sql
  SELECT AVG(papp) FROM consumption_data 
  WHERE HOUR(timestamp) >= 2 AND HOUR(timestamp) < 5
  ```
- **Affichage** : "Base nocturne : 120W (équipements permanents)"
- **Fiabilité** : ★★★★☆ (très représentatif)

##### 4.3 Écart semaine/week-end
- **Source** : Comparaison de la consommation moyenne
- **Calcul** :
  ```
  Moyenne_semaine = AVG(papp WHERE WEEKDAY(timestamp) IN (0,1,2,3,4))
  Moyenne_weekend = AVG(papp WHERE WEEKDAY(timestamp) IN (5,6))
  Écart = Moyenne_weekend - Moyenne_semaine
  ```
- **Affichage** : "Week-end : -15% de consommation"
- **Fiabilité** : ★★★★☆ (calcul statistique)

##### 4.4 Détection d'appareil resté allumé
- **Source** : Charge stable haute pendant une durée anormale
- **Calcul** :
  ```
  Détecter si:
    - papp reste dans une plage [P-50W, P+50W]
    - pendant > durée_seuil (ex: 2h)
    - où P > seuil_puissance (ex: 200W)
  ```
- **Affichage** : "Charge stable de 280W détectée depuis 3h15"
- **Fiabilité** : ★★★☆☆ (peut être normal)
- **Note** : Ne dit jamais "c'est la TV", juste "charge stable de X W"

---

### 5. Coût

**Objectif** : Calcul financier fiable basé sur les tarifs configurés

#### Indicateurs :

##### 5.1 Coût du jour/semaine/mois
- **Source** : Index HCHC/HCHP + tarifs
- **Calcul** :
  ```
  Conso_HC = HCHC_fin - HCHC_début
  Conso_HP = HCHP_fin - HCHP_début
  Coût = (Conso_HC * Tarif_HC) + (Conso_HP * Tarif_HP)
  ```
- **Affichage** : 3 cartes avec montant en €
- **Fiabilité** : ★★★★★ (si tarifs corrects)

##### 5.2 Coût projeté fin de mois
- **Source** : Extrapolation linéaire
- **Calcul** :
  ```
  Jours_écoulés = jour actuel du mois
  Jours_total = nombre de jours dans le mois
  Coût_projeté = (Coût_actuel / Jours_écoulés) * Jours_total
  ```
- **Affichage** : "Projection fin de mois : 67€"
- **Fiabilité** : ★★★☆☆ (dépend de la régularité)

##### 5.3 Courbe coût vs jours du mois
- **Source** : Coût cumulé par jour
- **Calcul** :
  ```
  Pour chaque jour D du mois:
    Coût_cumulé[D] = SUM(coûts des jours 1 à D)
  ```
- **Affichage** : Graphique linéaire avec ligne de budget
- **Fiabilité** : ★★★★★ (calcul exact)

---

## Implémentation technique

### Backend (PHP)

#### IndicateursController.php

```php
<?php
declare(strict_types=1);

namespace Statelec\Controller;

use Statelec\Service\IndicateursService;
use Statelec\Controller\SettingsController;

class IndicateursController
{
    private IndicateursService $service;

    public function __construct()
    {
        $this->service = new IndicateursService();
    }

    public function showIndicateurs(): array
    {
        // Récupérer tous les indicateurs
        $data = $this->service->getAllIndicateurs();

        return [
            'page_title' => 'Indicateurs Électriques',
            'currentPage' => 'indicateurs',
            'indicateurs' => $data,
            'theme' => SettingsController::getCurrentTheme()
        ];
    }

    public function getIndicateursData(): void
    {
        header('Content-Type: application/json');
        
        $periode = $_GET['periode'] ?? 'jour';
        $data = $this->service->getIndicateursByPeriode($periode);
        
        echo json_encode($data);
    }
}
```

#### IndicateursService.php

Service organisé en sections correspondant aux 5 catégories d'indicateurs.

**Méthodes principales** :
- `getMesuresElectriques()` : Catégorie 1
- `getStatsTemporelles()` : Catégorie 2
- `getEvenementsElectriques()` : Catégorie 3
- `getIndicateursGaspillage()` : Catégorie 4
- `getIndicateursCout()` : Catégorie 5

**Bonnes pratiques appliquées** :
- Types stricts activés
- Requêtes préparées pour toutes les requêtes SQL
- Gestion d'erreurs avec try/catch
- Validation des entrées
- Documentation PHPDoc
- Séparation des responsabilités

### Frontend (Twig + JavaScript)

#### Structure de la page

```twig
{% extends "base.twig" %}

{% block content %}
<main class="p-4 sm:p-6 space-y-6">
    <!-- Section 1: Mesures électriques brutes -->
    <section id="mesures-brutes">
        <!-- Cartes d'indicateurs -->
    </section>

    <!-- Section 2: Stats temporelles -->
    <section id="stats-temporelles">
        <!-- Graphiques et statistiques -->
    </section>

    <!-- Section 3: Événements électriques -->
    <section id="evenements">
        <!-- Liste des événements détectés -->
    </section>

    <!-- Section 4: Gaspillage -->
    <section id="gaspillage">
        <!-- Indicateurs de gaspillage -->
    </section>

    <!-- Section 5: Coût -->
    <section id="cout">
        <!-- Indicateurs financiers -->
    </section>
</main>
{% endblock %}
```

#### JavaScript

**Fonctionnalités** :
- Mise à jour temps réel de la puissance instantanée (SSE ou polling)
- Graphiques interactifs avec Chart.js
- Sélecteur de période (jour/semaine/mois)
- Rafraîchissement automatique des données

### Base de données

**Requêtes optimisées** :
- Index sur `timestamp` pour les requêtes temporelles
- Agrégations avec GROUP BY pour les statistiques horaires
- Utilisation de window functions pour les comparaisons de périodes

**Exemple de requête optimisée** :
```sql
-- Profil horaire moyen sur 30 jours
SELECT 
    HOUR(timestamp) as heure,
    AVG(papp) as puissance_moyenne,
    MAX(papp) as puissance_max,
    MIN(papp) as puissance_min,
    STDDEV(papp) as ecart_type
FROM consumption_data
WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY HOUR(timestamp)
ORDER BY heure;
```

## Configuration

### Paramètres ajustables

Les seuils suivants doivent être configurables dans les paramètres :

```php
// Dans settings table
[
    'seuil_saut_puissance' => 500,        // W
    'seuil_charge_elevee' => 2000,        // W
    'duree_charge_elevee' => 120,         // minutes
    'seuil_charge_stable' => 200,         // W
    'duree_charge_stable' => 120,         // minutes
    'tolerance_stabilite' => 50,          // W
    'tarif_hc' => 0.1821,                 // €/kWh
    'tarif_hp' => 0.2460,                 // €/kWh
    'budget_mensuel' => 50.0              // €
]
```

## Tests et validation

### Tests unitaires

Tester chaque méthode de calcul avec des données connues :
- Vérifier les calculs d'énergie
- Valider les détections d'événements
- Contrôler les calculs de coût

### Tests d'intégration

- Vérifier le chargement complet de la page
- Tester les appels API
- Valider l'affichage des graphiques

### Validation des données

- Comparer les résultats avec les données brutes
- Vérifier la cohérence temporelle
- Valider les calculs de coût avec une facture réelle

## Performance

### Optimisations

1. **Cache** : Mettre en cache les calculs lourds (profil horaire, stats mensuelles)
2. **Pagination** : Limiter le nombre d'événements affichés
3. **Agrégation** : Pré-calculer les statistiques horaires/journalières
4. **Index DB** : Index sur timestamp, HOUR(timestamp)

### Temps de réponse cibles

- Chargement initial : < 2s
- Mise à jour temps réel : < 500ms
- Changement de période : < 1s

## Évolutions futures

### Phase 2 (optionnel)

- Export des indicateurs en PDF/CSV
- Alertes configurables sur événements
- Comparaison multi-périodes
- Prédictions basées sur l'historique

### Phase 3 (optionnel)

- Détection automatique de patterns
- Suggestions d'optimisation
- Intégration avec API météo pour corrélations

## Glossaire

- **Puissance apparente (papp)** : Puissance instantanée mesurée en Watts
- **Index** : Compteur cumulatif en Wh (HCHC/HCHP)
- **PTEC** : Période tarifaire en cours (1=HC, 2=HP)
- **P90** : 90ème percentile, valeur dépassée seulement 10% du temps
- **Écart-type** : Mesure de la dispersion des valeurs
- **Plateau** : Période où la puissance reste stable

## Références

- [PHP Best Practices](./PHP_BP.md)
- [Documentation Chart.js](https://www.chartjs.org/)
- [Documentation Twig](https://twig.symfony.com/)
