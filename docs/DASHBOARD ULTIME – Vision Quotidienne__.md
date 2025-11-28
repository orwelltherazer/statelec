# **DASHBOARD ULTIME – Vision Quotidienne**

## 1\. **Résumé du jour (tout en haut, ultra lisible)**

- **Conso totale aujourd’hui :** 7.42 kWh
    
- **Coût estimé :** 1.58 €
    
- **Variation vs hier :** +12 %
    
- **Puissance moyenne :** 320 W
    
- **Base load (veilles) :** 87 W
    
- **Prévision fin de mois :** 52 € (±4 €)
    

> Un bandeau de couleur signale si c’est “Normal / Haut / Très élevê”.

* * *

## 2\. **Anomalies & Alertes**

Section essentielle, type “monitoring”.

- **Veille anormale détectée :** +35 W depuis 2 jours (nouvel appareil en veille ?)
    
- **Pic inhabituel :** +3600 W à 18:42 (four + plaques ?)
    
- **Cycle inattendu du chauffe-eau :** déclenché 3× au lieu de 1× cette nuit
    
- **Finition de charge :** aucun appareil resté à 0 W après une pointe de consommation
    
- **Index HP/HC incohérent** (si applicable)
    
- **Variation nocturne** trop élevée
    

> L’idée : tu ne fouilles RIEN, le dashboard te dit “ce qui mérite ton attention”.

* * *

## 3\. **Timeline intelligente (heure par heure)**

Graphique horizontal simple :

- Puissance instantanée lissée
    
- Événements annotés :
    
    - “Démarrage lave-linge”
        
    - “Four ON”
        
    - “Chauffe-eau cycle”
        
    - “Veille haute”
        
    - “Pic”
        
- Couleur codée par intensité (faible / moyenne / forte)
    

> En 10 secondes, tu relis toute ta journée.

* * *

## 4\. **Détail conso par appareils (heuristiques automatiques)**

Même si approximatif, hyper utile.

Tableau + donut :

- Chauffage : 2.8 kWh
    
- Eau chaude : 1.9 kWh
    
- Cuisine : 1.3 kWh
    
- Froid (frigo/congél) : 0.7 kWh
    
- Veilles : 0.5 kWh
    
- Divers : 0.2 kWh
    

> Même si 10–20 % d’erreur, ça suffit pour comprendre les tendances.

* * *

## 5\. **Graph de puissance en temps réel (dernière heure)**

- Ligne brute
    
- Ligne lissée
    
- Repérage automatique des hausses/baisses
    
- Indication du “niveau plancher”
    

> C’est ton “ECG électrique”.

* * *

## 6\. **Conso cumulée (jour / semaine / mois)**

Trois mini-graphes côte à côte :

### A. **Aujourd’hui (heures)**

Histogramme 24 barres  
→ repère les moments d’activité

### B. **Cette semaine (jours)**

Bar chart 7 barres  
→ compare les journées

### C. **Ce mois (jours cumulés)**

Courbe avec tendance  
→ projection automatique

* * *

## 7\. **Veilles & consommation passive**

Section clé car c’est là qu’on économise le plus.

- **Base load actuel : 87 W**
    
- **Base load optimal historique : 45 W**
    
- **Surcoût estimé : 12 €/mois**
    
- Liste des moments où la base load est montée (indice d’un appareil branché).
    

* * *

## 8\. **Machine-learning light : “Insights du jour”**

Rien d’exotique, juste des heuristiques intelligentes :

- “Le frigo semble consommer +10 % par rapport à sa moyenne.”
    
- “Ton chauffe-eau a tourné plus longtemps que d’habitude.”
    
- “Tu consommes 14 % plus entre 18h et 20h qu’il y a 15 jours.”
    
- “Nouvel appareil détecté vers 22h : cycle de 45 min, pic +1600 W.”
    
- “Ta conso en veille fait 34 % de ta conso quotidienne : assez élevée.”
    

> C’est la partie qui donne la sensation d’un assistant énergétique.

* * *

## 9\. **Coût journalier comparé (mois glissant)**

Graph très simple : barres verticales.

- Coût par jour sur 30 jours
    
- Couleur différente pour HP et HC
    
- Petit indicateur “jour le moins cher / le plus cher”
    

* * *

## 10\. **Prévisions**

Algorithme simple (moyennes mobiles).

- Budget fin de mois
    
- Conso prévue demain (basée sur les 7 derniers jours)
    
- Détection de dérives
    
- “Si tu continues comme ça, tu seras à +15 % ce mois-ci.”
    

* * *

# **Résumé conceptuel**

Ton dashboard doit donner :

- **Vision immédiate** : où j’en suis ?
    
- **Explication** : pourquoi ça a consommé ?
    
- **Anomalies** : qu’est-ce qui cloche ?
    
- **Tendances** : suis-je dans les normes ?
    
- **Projection** : où vais-je arriver ?