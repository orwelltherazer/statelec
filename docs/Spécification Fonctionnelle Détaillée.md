# Spécification Fonctionnelle Détaillée

## 1\. Vision Générale du Projet

Cette application permet d’exploiter les données fournies par un compteur Linky (puissance instantanée, index HP/HC, tarif en cours, horloge TIC) afin d’offrir une plateforme complète d’analyse énergétique, incluant temps réel, historiques, alertes, prévisions et analyses comportementales. Le produit doit être fiable, rapide, ergonomique, responsive et exploitable par un utilisateur non technique.

La solution repose sur PHP (backend), Twig (templating), TailwindCSS (UI), MySQL (stockage), Chart.js (visualisation).

Objectifs :

- Comprendre la consommation en temps réel et sur historique.
    
- Identifier anomalies, pics, veilles énergétiques.
    
- Estimer les coûts et anticiper les dérives.
    
- Faciliter la prise de décision (économies, optimisation tarifaire).
    
- Offrir un tableau de bord unique synthétisant l’essentiel.
    

* * *

## 2\. Fonctionnalités Détaillées

### 2.1 Acquisition des données

- Récupération continue via API interne alimentée par un microcontrôleur (Wemos D1).
    
- Format attendu :
    
    - Horodatage
        
    - Puissance instantanée (W)
        
    - Index HP et HC (Wh)
        
    - Indicateur tarifaire (HP=1, HC=0)
        
    - Option : tension, intensité si ajoutées côté capteur
        
- Gestion des erreurs et validation des données.
    
- Journalisation des anomalies de trames.
    

### 2.2 Analyses automatiques

- Calcul de consommation minute → heure → jour → mois.
    
- Détection des événements :
    
    - pics > X W en moins de Y secondes
        
    - creux anormaux
        
    - veilles persistantes (niveau stable > N minutes)
        
    - incohérences Linky (index non monotones)
        
    - surconsommation par rapport à moyenne historique
        
- Prévisions :
    
    - estimation fin de journée
        
    - estimation facture mensuelle
        
    - projection sur variations tarifaires
        

### 2.3 Alertes

- Alertes configurables :
    
    - dépassement de puissance
        
    - consommation journalière excessive
        
    - anomalie Linky
        
    - veille prolongée
        
- Système de notification par email ou webhook.
    
- Historisation des alertes déclenchées.
    

### 2.4 Visualisation / Dashboard

- Affichage en temps réel (<2 secondes de rafraîchissement).
    
- Graphiques avancés via Chart.js incluant : zones HP/HC, heatmap hebdomadaire, histogrammes.
    

### 2.5 Historisation

- Accès aux données : jour, semaine, mois, année.
    
- Comparaisons entre périodes.
    
- Export CSV/PDF.
    

### 2.6 Paramétrage utilisateur

- Tarifs HP/HC (€/kWh).
    
- Seuils d’alertes.
    
- Fréquences de rafraîchissement.
    
- Rétention des données (durée de conservation DB).
    

* * *

## 3\. Structure Base de Données Détaillée

### 3.1 `elec_data`

Données brutes Linky collectées.

- id INT PK
    
- date_time DATETIME indexé
    
- power_w DECIMAL(10,2)
    
- index_hp DECIMAL(10,2)
    
- index_hc DECIMAL(10,2)
    
- tarif_type TINYINT (0=HC,1=HP)
    
- created_at TIMESTAMP
    

### 3.2 `daily_stats`

Agrégations quotidiennes.

- id INT PK
    
- date DATE unique
    
- conso_hp DECIMAL
    
- conso_hc DECIMAL
    
- conso_total DECIMAL
    
- cout_estime DECIMAL
    
- pic_w INT
    
- creux_w INT
    
- duree_pics INT (secondes cumulées)
    
- duree_veille INT
    

### 3.3 `events`

- id INT PK
    
- date_time DATETIME
    
- type VARCHAR
    
- valeur_w INT
    
- niveau VARCHAR (info/avertissement/critique)
    
- message TEXT
    

### 3.4 `alerts`

- id INT PK
    
- type VARCHAR
    
- seuil DECIMAL
    
- actif TINYINT
    
- derniere_notification DATETIME
    

### 3.5 `settings`

Clés simples.

- cle VARCHAR
    
- valeur VARCHAR
    

* * *

## 4\. Description Fonctionnelle des Écrans (Détaillé)

### 4.1 Écran 1 — **Dashboard Ultime** (page d’accueil)

Objectif : synthèse complète en un coup d’œil.

#### Sections :

1.  **Puissance instantanée**
    
    - compteur XXL + jauge dynamique
        
    - indicateur couleur (normale / élevée / critique)
        
2.  **Tarif en cours (HP/HC)**
    
    - badge couleur + temps avant changement
3.  **Graphique temps réel**
    
    - dernière 1h
        
    - zones HP/HC colorisées
        
    - moyenne glissante
        
4.  **Consommation du jour**
    
    - kWh HP, kWh HC
        
    - total
        
    - coût estimé
        
    - % par rapport à moyenne historique du même jour de semaine
        
5.  **Prévision fin de journée**
    
    - estimation kWh
        
    - estimation coût
        
    - comparaison J-7 / J-30
        
6.  **Graphique journalier (24 h)**
    
    - puissance instantanée
        
    - index visibles en tooltips
        
7.  **Évènements récents**
    
    - liste chronologique avec icônes
        
    - filtrable (pics, anomalies…)
        
8.  **Score énergie du jour**
    
    - barème 0 à 100
        
    - basé sur conso > moyenne + pics
        

### 4.2 Écran 2 — Historique Journalier

- Sélecteur date (calendrier)
    
- Graphique complet de la journée
    
- Détail :
    
    - conso HP/HC
        
    - courbe puissance haute précision
        
    - répartition par plages horaires
        
- Liste événements du jour
    

### 4.3 Écran 3 — Vue Hebdomadaire / Mensuelle / Annuelle

- Barres de consommation
    
- Moyenne glissante 7j / 30j
    
- Coût total estimé
    
- Top jours les plus énergivores
    
- Heatmap puissance par heure/jour
    

### 4.4 Écran 4 — Analyse Avancée

#### Modules :

1.  **Veilles énergétiques**
    
    - détection des consommations constantes > X W
        
    - estimation du coût annuel
        
2.  **Pics énergétiques**
    
    - histogramme
        
    - causes probables (heuristique)
        
3.  **Analyse comportementale**
    
    - comparaison matin / soir
        
    - profils répétitifs
        
4.  **Transition HP ↔ HC**
    
    - exploitation optimale ?
        
    - estimation des économies possibles
        

### 4.5 Écran 5 — Alertes

- Liste alertes actives
    
- Historique
    
- Création / édition alertes
    
- Simulation d’impact (exemple : seuil puissance trop bas → trop d’alertes)
    

### 4.6 Écran 6 — Paramètres

- Tarif électricité
    
- Seuils alertes
    
- Options graphiques
    
- Gestion rétention données
    
- Import/Export config JSON
    

* * *

## 5\. Chart.js — Pack Graphiques

- Courbes multipaliers
    
- Zones colorées HP/HC
    
- Heatmap plugin
    
- Histogrammes événements
    
- Donuts HP/HC
    
- Graphiques responsives optimisés mobile
    

* * *

## 6\. Workflow Backend Complet

1.  **API de réception** des données Linky
    
2.  Normalisation / stockage
    
3.  CRON :
    
    - calcul stats journalières
        
    - génération événements
        
    - nettoyage
        
4.  Exposition endpoints internes sécurisés
    
5.  Cache redis (optionnel)
    

* * *

## 7\. ToDo Détaillé — Phase par Phase

### Phase A — Base Technique

- Mise en place architecture PHP + templates Twig
    
- Installation Tailwind + config
    
- Installation Chart.js
    
- Mise en place DB + migrations
    

### Phase B — Backend Acquisition

- API /ingest
    
- Validation données
    
- Protection anti-doublons
    

### Phase C — CRONs et Analyses

- script stats quotidiennes
    
- script événements intelligents
    
- script prévisions
    

### Phase D — Front-End Pages

#### Dashboard

- cartes stats + graphs
    
- évènements + score
    

#### Historique

- calendrier + graph journée

#### Semaine/Mois

- comparatifs + heatmap

#### Analyse avancée

- modules veilles / pics / comportements

#### Alertes

- CRUD alertes

#### Paramètres

- formulaires + validations

### Phase E — Responsive

- refonte mobile
    
- menus bottom
    
- graphes simplifiés
    

### Phase F — Optimisation / QA

- stress tests API
    
- optimisation requêtes SQL
    
- tests UX
    

* * *

## 8\. Extensions futures

- IA de reconnaissance d’appareils
    
- Intégration Home Assistant
    
- Widgets Android/iOS
    
- Mode “Eco coach” quotidien
    

Ce document constitue une base complète destinée à un développeur pour construire l’intégralité de l’application.