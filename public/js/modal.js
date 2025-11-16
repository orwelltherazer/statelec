/**
 * Système de modal pour les retours d'enregistrement
 */
class ModalSystem {
    constructor() {
        this.modal = null;
        this.init();
    }

    init() {
        // Créer la modal si elle n'existe pas
        if (!document.getElementById('resultModal')) {
            this.createModal();
        }
        this.modal = document.getElementById('resultModal');
    }

    createModal() {
        const modalHTML = `
            <div id="resultModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-icon" id="modalIcon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6L9 17l-5-5"></path>
                            </svg>
                        </div>
                        <h3 class="modal-title" id="modalTitle">Titre</h3>
                    </div>
                    <div class="modal-body">
                        <p class="modal-message" id="modalMessage">Message</p>
                        <div class="modal-details" id="modalDetails" style="display: none;">
                            <h4 class="modal-details-title">Détails de l'enregistrement</h4>
                            <div id="modalDetailsContent"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="modal-btn modal-btn-secondary" onclick="modalSystem.hide()">Fermer</button>
                        <button class="modal-btn modal-btn-primary" id="modalPrimaryBtn" onclick="modalSystem.hide()">OK</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    show(type, title, message, details = null, primaryBtnText = 'OK') {
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalDetails = document.getElementById('modalDetails');
        const modalDetailsContent = document.getElementById('modalDetailsContent');
        const modalPrimaryBtn = document.getElementById('modalPrimaryBtn');

        // Configurer l'icône et les couleurs selon le type
        modalIcon.className = `modal-icon ${type}`;
        
        // Configurer l'icône SVG selon le type
        let iconSVG = '';
        switch(type) {
            case 'success':
                iconSVG = '<path d="M20 6L9 17l-5-5"></path>';
                break;
            case 'error':
                iconSVG = '<path d="M18 6L6 18M6 6l12 12"></path>';
                break;
            case 'warning':
                iconSVG = '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>';
                break;
            case 'info':
                iconSVG = '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>';
                break;
        }
        modalIcon.innerHTML = iconSVG;

        // Configurer le contenu
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        modalPrimaryBtn.textContent = primaryBtnText;

        // Afficher les détails si fournis
        if (details) {
            modalDetails.style.display = 'block';
            let detailsHTML = '';
            for (const [key, value] of Object.entries(details)) {
                detailsHTML += `
                    <div class="modal-detail-item">
                        <span class="modal-detail-label">${key}:</span>
                        <span class="modal-detail-value">${value}</span>
                    </div>
                `;
            }
            modalDetailsContent.innerHTML = detailsHTML;
        } else {
            modalDetails.style.display = 'none';
        }

        // Afficher la modal
        this.modal.classList.add('show');

        // Masquer les boutons pour les succès
        const modalFooter = this.modal.querySelector('.modal-footer');
        if (type === 'success') {
            modalFooter.style.display = 'none';
            setTimeout(() => this.hide(), 3000);
        } else {
            modalFooter.style.display = 'flex';
        }

        // Fermeture au clic sur le fond
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.hide();
            }
        });
    }

    hide() {
        this.modal.classList.remove('show');
    }

    // Méthodes pratiques
    success(title, message, details = null) {
        this.show('success', title, message, details);
    }

    error(title, message, details = null) {
        this.show('error', title, message, details);
    }

    warning(title, message, details = null) {
        this.show('warning', title, message, details);
    }

    info(title, message, details = null) {
        this.show('info', title, message, details);
    }
}

// Instance globale
let modalSystem;