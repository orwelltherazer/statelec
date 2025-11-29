# Guide d'utilisation des Loaders

Ce guide explique comment utiliser les animations de chargement dans l'application Statelec.

## 📁 Fichiers créés

1. **`public/css/loader.css`** - Styles CSS pour les animations
2. **`public/js/loader.js`** - Gestionnaire JavaScript pour les loaders
3. **Modifications dans `templates/base.twig`** - Inclusion des fichiers CSS et JS

## 🎨 Types de loaders disponibles

### 1. Loader Spinner (Classique)
Un spinner circulaire tournant - simple et élégant.

```html
<div class="chart-loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">
        Chargement<span class="loader-dots"></span>
    </div>
</div>
```

### 2. Loader Électrique (Thématique) ⚡
Un éclair animé avec cercle tournant - parfait pour une application électrique !

```html
<div class="chart-loader">
    <div class="loader-electric"></div>
    <div class="loader-text">
        Chargement des données<span class="loader-dots"></span>
    </div>
</div>
```

### 3. Loader Barres
Des barres qui rebondissent - dynamique et moderne.

```html
<div class="page-loader">
    <div class="loader-bars">
        <div class="loader-bar"></div>
        <div class="loader-bar"></div>
        <div class="loader-bar"></div>
        <div class="loader-bar"></div>
        <div class="loader-bar"></div>
    </div>
    <div class="loader-text">
        Chargement<span class="loader-dots"></span>
    </div>
</div>
```

## 💻 Utilisation JavaScript

### Méthode simple

```javascript
// Afficher le loader
document.getElementById('loading-indicator').classList.remove('hidden');

// Masquer le loader
document.getElementById('loading-indicator').classList.add('hidden');
```

### Avec LoaderManager (Recommandé)

```javascript
// Afficher un loader
LoaderManager.show('#loading-indicator');

// Masquer un loader
LoaderManager.hide('#loading-indicator');

// Avec une promesse (automatique)
await LoaderManager.withLoader(
    fetch('/api/data'),
    '#loading-indicator'
);

// Loader de page complet
LoaderManager.showPageLoader('Chargement des données', 'spinner');
// ... faire quelque chose ...
LoaderManager.hidePageLoader();
```

## 📄 Intégration dans une page Twig

### Exemple complet (comme dans historique.twig)

```twig
{% extends "base.twig" %}

{% block content %}
<main class="p-2 sm:p-3 space-y-3">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h1>Ma Page</h1>
        
        {# Loader pour les graphiques #}
        <div id="loading-indicator" class="chart-loader hidden">
            <div class="loader-electric"></div>
            <div class="loader-text">
                Chargement des graphiques<span class="loader-dots"></span>
            </div>
        </div>
        
        {# Contenu des graphiques #}
        <div id="chart-section">
            <canvas id="myChart"></canvas>
        </div>
    </div>
</main>
{% endblock %}

{% block scripts %}
<script>
    document.addEventListener('DOMContentLoaded', async function() {
        const loadingIndicator = document.getElementById('loading-indicator');
        const chartSection = document.getElementById('chart-section');
        
        // Afficher le loader
        loadingIndicator.classList.remove('hidden');
        chartSection.classList.add('hidden');
        
        try {
            // Charger les données
            const response = await fetch('/api/data');
            const data = await response.json();
            
            // Créer le graphique
            // ...
            
        } catch (error) {
            console.error('Erreur:', error);
        } finally {
            // Masquer le loader
            loadingIndicator.classList.add('hidden');
            chartSection.classList.remove('hidden');
        }
    });
</script>
{% endblock %}
```

## 🎯 Pages à mettre à jour

Voici les pages qui bénéficieraient de ces loaders :

### ✅ Déjà implémenté
- [x] **historique.twig** - Loader électrique avec éclair

### 📋 À implémenter
- [ ] **dashboard.twig** - Pour le chargement du graphique
- [ ] **cout.twig** - Pour les graphiques de coûts
- [ ] **indicateurs.twig** - Pour les indicateurs électriques
- [ ] **analyse.twig** - Pour les analyses
- [ ] **diagnostic.twig** - Pour les diagnostics
- [ ] **alertes.twig** - Si des graphiques sont présents

## 🎨 Personnalisation

### Changer la couleur du loader

Dans `loader.css`, modifiez les couleurs :

```css
.loader-spinner {
    border-top-color: #3b82f6; /* Bleu par défaut */
}

/* Pour une autre couleur */
.loader-spinner.green {
    border-top-color: #10b981; /* Vert */
}
```

### Changer la vitesse d'animation

```css
.loader-spinner {
    animation: spin 0.8s linear infinite; /* 0.8s par défaut */
}

/* Plus rapide */
.loader-spinner.fast {
    animation: spin 0.5s linear infinite;
}

/* Plus lent */
.loader-spinner.slow {
    animation: spin 1.5s linear infinite;
}
```

### Changer le texte dynamiquement

```javascript
const loaderText = document.querySelector('#loading-indicator .loader-text');
loaderText.childNodes[0].textContent = 'Nouveau texte';
```

## 🌓 Support du mode sombre

Les loaders s'adaptent automatiquement au thème :
- Classe `.dark` ajoutée automatiquement
- Couleurs adaptées pour chaque mode
- Transparence ajustée pour une meilleure lisibilité

## 📱 Responsive

Les loaders sont entièrement responsive :
- Tailles adaptées aux écrans mobiles
- Animations fluides sur tous les appareils
- Pas de surcharge de performance

## ⚡ Performance

- Animations CSS pures (pas de JavaScript)
- Transitions optimisées avec `transform` et `opacity`
- Pas d'impact sur les performances de la page
- Chargement asynchrone des ressources

## 🐛 Dépannage

### Le loader ne s'affiche pas
1. Vérifier que `loader.css` est bien chargé
2. Vérifier que la classe `hidden` est bien retirée
3. Vérifier la console pour les erreurs

### Le loader ne disparaît pas
1. Vérifier que `classList.add('hidden')` est bien appelé
2. Vérifier qu'il n'y a pas d'erreur dans le `finally` block
3. Utiliser `LoaderManager.hide()` pour plus de fiabilité

### Le loader ne s'adapte pas au thème
1. Vérifier que la classe `dark` est sur `<html>`
2. Forcer le rafraîchissement avec `LoaderManager.show()`

## 📚 Exemples d'utilisation

### Exemple 1 : Chargement de données API

```javascript
async function loadData() {
    LoaderManager.show('#my-loader');
    
    try {
        const data = await fetch('/api/consumption').then(r => r.json());
        updateChart(data);
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur de chargement');
    } finally {
        LoaderManager.hide('#my-loader');
    }
}
```

### Exemple 2 : Chargement multiple

```javascript
async function loadAllData() {
    const loader = LoaderManager.createPageLoader('Chargement de toutes les données', 'bars');
    document.body.appendChild(loader);
    
    try {
        await Promise.all([
            loadConsumption(),
            loadCosts(),
            loadAlerts()
        ]);
    } finally {
        LoaderManager.hidePageLoader();
    }
}
```

### Exemple 3 : Loader pour un graphique spécifique

```javascript
async function loadChart(chartId) {
    const chartContainer = document.getElementById(chartId);
    const loader = LoaderManager.createChartLoader('Chargement du graphique');
    
    chartContainer.appendChild(loader);
    
    try {
        const data = await fetchChartData(chartId);
        renderChart(chartId, data);
    } finally {
        loader.remove();
    }
}
```

## 🎉 Résultat

Vous avez maintenant un système de loaders professionnel et réutilisable qui :
- ✅ Améliore l'expérience utilisateur
- ✅ S'adapte au thème de l'application
- ✅ Est facile à utiliser et à personnaliser
- ✅ Fonctionne sur toutes les pages
- ✅ Est optimisé pour les performances
