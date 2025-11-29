/**
 * Utilitaire pour gérer les loaders de chargement
 */

class LoaderManager {
    /**
     * Affiche un loader
     * @param {string|HTMLElement} element - Sélecteur CSS ou élément DOM
     */
    static show(element) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (el) {
            el.classList.remove('hidden');
            // Adapter au thème actuel
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                el.classList.add('dark');
            } else {
                el.classList.remove('dark');
            }
        }
    }

    /**
     * Masque un loader
     * @param {string|HTMLElement} element - Sélecteur CSS ou élément DOM
     */
    static hide(element) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (el) {
            el.classList.add('hidden');
        }
    }

    /**
     * Crée un loader de page complet
     * @param {string} text - Texte à afficher (optionnel)
     * @param {string} type - Type de loader: 'spinner' ou 'bars'
     * @returns {HTMLElement}
     */
    static createPageLoader(text = 'Chargement', type = 'spinner') {
        const isDark = document.documentElement.classList.contains('dark');
        const loader = document.createElement('div');
        loader.className = `page-loader ${isDark ? 'dark' : ''}`;
        loader.id = 'page-loader';

        if (type === 'bars') {
            loader.innerHTML = `
                <div class="loader-bars">
                    <div class="loader-bar"></div>
                    <div class="loader-bar"></div>
                    <div class="loader-bar"></div>
                    <div class="loader-bar"></div>
                    <div class="loader-bar"></div>
                </div>
                <div class="loader-text">${text}<span class="loader-dots"></span></div>
            `;
        } else {
            loader.innerHTML = `
                <div class="loader-spinner"></div>
                <div class="loader-text">${text}<span class="loader-dots"></span></div>
            `;
        }

        return loader;
    }

    /**
     * Crée un loader pour un graphique spécifique
     * @param {string} text - Texte à afficher (optionnel)
     * @returns {HTMLElement}
     */
    static createChartLoader(text = 'Chargement du graphique') {
        const isDark = document.documentElement.classList.contains('dark');
        const loader = document.createElement('div');
        loader.className = `chart-loader ${isDark ? 'dark' : ''}`;

        loader.innerHTML = `
            <div class="loader-spinner"></div>
            <div class="loader-text">${text}<span class="loader-dots"></span></div>
        `;

        return loader;
    }

    /**
     * Affiche un loader de page complet
     * @param {string} text - Texte à afficher
     * @param {string} type - Type de loader
     */
    static showPageLoader(text = 'Chargement', type = 'spinner') {
        // Supprimer l'ancien loader s'il existe
        const existingLoader = document.getElementById('page-loader');
        if (existingLoader) {
            existingLoader.remove();
        }

        const loader = this.createPageLoader(text, type);
        document.body.appendChild(loader);
        
        // Forcer un reflow pour que la transition fonctionne
        loader.offsetHeight;
        
        // Retirer la classe hidden après un court délai
        setTimeout(() => {
            loader.classList.remove('hidden');
        }, 10);
    }

    /**
     * Masque le loader de page
     */
    static hidePageLoader() {
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.classList.add('hidden');
            // Supprimer l'élément après la transition
            setTimeout(() => {
                loader.remove();
            }, 300);
        }
    }

    /**
     * Enveloppe une promesse avec un loader
     * @param {Promise} promise - La promesse à exécuter
     * @param {string|HTMLElement} loaderElement - Le loader à afficher
     * @returns {Promise}
     */
    static async withLoader(promise, loaderElement) {
        this.show(loaderElement);
        try {
            const result = await promise;
            return result;
        } finally {
            this.hide(loaderElement);
        }
    }

    /**
     * Enveloppe une promesse avec un loader de page
     * @param {Promise} promise - La promesse à exécuter
     * @param {string} text - Texte du loader
     * @param {string} type - Type de loader
     * @returns {Promise}
     */
    static async withPageLoader(promise, text = 'Chargement', type = 'spinner') {
        this.showPageLoader(text, type);
        try {
            const result = await promise;
            return result;
        } finally {
            this.hidePageLoader();
        }
    }
}

// Exporter pour utilisation globale
if (typeof window !== 'undefined') {
    window.LoaderManager = LoaderManager;
}
