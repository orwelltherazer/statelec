# 🎨 Thème Adminty pour Statelec

## Vue d'ensemble

Le thème **Adminty** est un thème moderne et coloré inspiré du dashboard Adminty. Il apporte une interface vibrante et professionnelle à votre application Statelec.

![Adminty Theme Preview](../screenshot_adminty.png)

## 🌈 Palette de couleurs

Le thème utilise une palette de couleurs vibrantes et modernes :

| Couleur | Hex | Usage |
|---------|-----|-------|
| 🟠 Orange | `#FF9F43` | Consommation, statistiques principales |
| 🟢 Vert | `#00D97E` | Coûts, économies, succès |
| 🩷 Rose | `#F672A7` | Puissance, alertes importantes |
| 🔵 Cyan | `#39C0ED` | Alertes, notifications, accents |
| 🟣 Violet | `#7367F0` | Analyses, graphiques secondaires |

## 📁 Fichiers du thème

### Fichiers créés

1. **`templates/components/nav_sidebar_adminty.twig`**
   - Sidebar avec gradient bleu-gris foncé
   - Navigation avec badges colorés
   - Avatar utilisateur en bas
   - Logo avec icône gradient

2. **`public/css/adminty-theme.css`**
   - Styles pour les cartes statistiques
   - Badges et pills colorés
   - Gradients et animations
   - Classes utilitaires Adminty

3. **`templates/pages/dashboard_adminty.twig`**
   - Dashboard exemple avec le style Adminty
   - Cartes statistiques colorées
   - Graphiques avec palette Adminty

## 🚀 Activation du thème

### Option 1 : Via les paramètres (Recommandé)

1. Allez dans **Paramètres** de l'application
2. Dans la section **Thème**, sélectionnez **"Adminty Side"**
3. Cliquez sur **Enregistrer**

### Option 2 : Modification manuelle

1. **Inclure le CSS Adminty dans `base.twig`** :

```twig
{# Dans le <head> #}
<link rel="stylesheet" href="{{ basePath }}css/adminty-theme.css">
```

2. **Utiliser la sidebar Adminty** :

Modifiez `base.twig` pour utiliser la nouvelle sidebar :

```twig
{% if 'Adminty' in theme %}
    {% include 'components/nav_sidebar_adminty.twig' %}
{% elseif 'Side' in theme %}
    {% include 'components/nav_sidebar.twig' %}
{% else %}
    {% include 'components/nav_header.twig' %}
{% endif %}
```

3. **Adapter le fond de page** :

```twig
<body class="{% if 'Adminty' in theme %}adminty-page-bg{% else %}bg-gray-100 dark:bg-gray-900{% endif %}">
```

## 🎨 Utilisation des composants

### Cartes statistiques

```html
<div class="adminty-card orange">
    <div class="adminty-card-header">
        <span class="adminty-card-title">Titre de la carte</span>
        <div class="adminty-card-icon">
            <!-- Icône SVG -->
        </div>
    </div>
    <div class="adminty-card-value">1,234</div>
    <div class="adminty-card-footer">
        <span class="adminty-badge orange">
            <!-- Icône flèche -->
            +12.5%
        </span>
        <span class="text-xs text-gray-500">vs hier</span>
    </div>
</div>
```

**Variantes de couleurs** : `orange`, `green`, `pink`, `cyan`

### Badges de changement

```html
<!-- Badge positif (vert) -->
<span class="adminty-badge green">
    <svg><!-- Icône flèche vers le haut --></svg>
    +12.5%
</span>

<!-- Badge négatif (rose) -->
<span class="adminty-badge pink">
    <svg><!-- Icône flèche vers le bas --></svg>
    -5.2%
</span>
```

### Cartes de graphiques

```html
<div class="adminty-chart-card">
    <div class="flex items-center justify-between mb-4">
        <h2 class="adminty-chart-title">Titre du graphique</h2>
        <div class="adminty-chart-actions">
            <button class="adminty-btn-icon">
                <!-- Icône -->
            </button>
        </div>
    </div>
    <div style="width: 100%; height: 300px;">
        <canvas id="myChart"></canvas>
    </div>
</div>
```

### Grille de statistiques

```html
<div class="adminty-stats-grid">
    <!-- Vos cartes statistiques ici -->
    <!-- Elles s'adapteront automatiquement en responsive -->
</div>
```

## 🎯 Personnalisation des graphiques

Utilisez la palette de couleurs Adminty dans vos graphiques Chart.js :

```javascript
const colors = {
    orange: '#FF9F43',
    green: '#00D97E',
    pink: '#F672A7',
    cyan: '#39C0ED',
    purple: '#7367F0'
};

new Chart(ctx, {
    data: {
        datasets: [{
            borderColor: colors.cyan,
            backgroundColor: colors.cyan + '20', // Ajoute transparence
            // ...
        }]
    }
});
```

## 📱 Responsive Design

Le thème Adminty est entièrement responsive :

- **Desktop** : Sidebar fixe à gauche, grille de 4 colonnes pour les stats
- **Tablet** : Grille de 2 colonnes
- **Mobile** : Sidebar en overlay, grille de 1 colonne

## 🎭 Caractéristiques du thème

### Sidebar

- ✅ Gradient bleu-gris foncé élégant
- ✅ Logo avec icône gradient cyan-bleu
- ✅ Navigation avec états hover et actif
- ✅ Badges "NEW" et "HOT" pour les nouveautés
- ✅ Avatar utilisateur avec gradient
- ✅ Animations fluides

### Cartes

- ✅ Bordure colorée en haut de chaque carte
- ✅ Icônes avec gradient assorti
- ✅ Effet hover avec élévation
- ✅ Animations d'apparition en cascade
- ✅ Ombres douces et modernes

### Badges

- ✅ Gradients colorés
- ✅ Ombres colorées assorties
- ✅ Icônes intégrées
- ✅ Coins arrondis

## 🔧 Variables CSS personnalisables

Vous pouvez personnaliser les couleurs dans `adminty-theme.css` :

```css
:root {
    --adminty-orange: #FF9F43;
    --adminty-green: #00D97E;
    --adminty-pink: #F672A7;
    --adminty-cyan: #39C0ED;
    --adminty-purple: #7367F0;
    
    --adminty-sidebar-from: #3d4465;
    --adminty-sidebar-to: #2d3347;
}
```

## 📋 Checklist d'intégration

Pour intégrer complètement le thème Adminty :

- [ ] Inclure `adminty-theme.css` dans `base.twig`
- [ ] Ajouter l'option "Adminty Side" dans les paramètres
- [ ] Modifier la logique de sélection de sidebar dans `base.twig`
- [ ] Adapter les pages existantes avec les classes Adminty
- [ ] Mettre à jour les graphiques avec la palette Adminty
- [ ] Tester le responsive sur mobile/tablet
- [ ] Vérifier les animations et transitions

## 🎨 Exemples de pages

### Dashboard

Voir `templates/pages/dashboard_adminty.twig` pour un exemple complet avec :
- 4 cartes statistiques colorées
- Graphique principal de consommation
- Graphique en donut pour la répartition
- Graphique en barres pour les tendances

### Autres pages à adapter

Pour adapter vos autres pages au style Adminty :

1. **Remplacer les cartes blanches** par des `adminty-card`
2. **Utiliser la palette de couleurs** Adminty pour les graphiques
3. **Ajouter des badges** pour les variations et changements
4. **Utiliser `adminty-page-bg`** comme fond de page

## 🐛 Dépannage

### Les couleurs ne s'affichent pas

1. Vérifier que `adminty-theme.css` est bien chargé
2. Vérifier l'ordre de chargement des CSS (Adminty doit être après Tailwind)
3. Vider le cache du navigateur

### La sidebar ne s'affiche pas correctement

1. Vérifier que la condition `{% if 'Adminty' in theme %}` fonctionne
2. Vérifier que le thème est bien défini dans la session
3. Vérifier les classes Tailwind (lg:pl-56 pour le décalage)

### Les animations ne fonctionnent pas

1. Vérifier que les animations CSS sont supportées
2. Désactiver les préférences "Réduire les animations" du système
3. Tester dans un autre navigateur

## 📚 Ressources

- **Inspiration** : [Adminty Dashboard](https://adminty.com)
- **Icônes** : Feather Icons (déjà utilisées)
- **Graphiques** : Chart.js avec palette personnalisée

## 🎉 Résultat attendu

Avec le thème Adminty, votre application Statelec aura :

- 🎨 Une interface moderne et colorée
- 📊 Des graphiques visuellement attrayants
- 💳 Des cartes statistiques professionnelles
- 🎭 Une sidebar élégante et fonctionnelle
- ✨ Des animations fluides et agréables
- 📱 Un design entièrement responsive

Profitez de votre nouveau thème Adminty ! 🚀
