document.addEventListener('DOMContentLoaded', function() {
    // Validation des données
    function validateSettings(data, formType) {
        const errors = [];
        const warnings = [];

        // Validation pour les paramètres généraux
        if (formType === 'general') {
            if (data.prixHC !== undefined) {
                if (data.prixHC < 0 || data.prixHC > 2) {
                    errors.push('Le prix HC doit être entre 0€ et 2€');
                }
            }
            if (data.prixHP !== undefined) {
                if (data.prixHP < 0 || data.prixHP > 2) {
                    errors.push('Le prix HP doit être entre 0€ et 2€');
                }
            }
            if (data.budgetMensuel !== undefined) {
                if (data.budgetMensuel < 0 || data.budgetMensuel > 1000) {
                    errors.push('Le budget mensuel doit être entre 0€ et 1000€');
                }
            }
        }
        
        if (formType === 'thingspeak') {
            if (data.apiUrl !== undefined && data.apiUrl) {
                try {
                    new URL(data.apiUrl);
                } catch {
                    errors.push('L\'URL de l\'API n\'est pas valide');
                }
            }
        }

        // Validation pour les alertes
        if (formType === 'alerts') {
            if (data.email_alerts && !data.email_destinataire) {
                errors.push('L\'adresse email destinataire est obligatoire si les alertes sont activées');
            }
            if (data.email_destinataire) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(data.email_destinataire)) {
                    errors.push('L\'adresse email n\'est pas valide');
                }
            }
            if (data.seuilPuissance !== undefined && data.seuilPuissance < 0) {
                errors.push('Le seuil de puissance doit être positif');
            }
            if (data.seuilJournalier !== undefined && data.seuilJournalier < 0) {
                errors.push('Le seuil journalier doit être positif');
            }
            if (data.seuilPuissance > 0 && data.seuilPuissance < 100) {
                warnings.push('Le seuil de puissance semble très bas (moins de 100W)');
            }
            if (data.seuilJournalier > 0 && data.seuilJournalier > 100) {
                warnings.push('Le seuil journalier semble très élevé (plus de 100kWh)');
            }
        }

        return { errors, warnings };
    }

    // Fonction améliorée pour sauvegarder les paramètres
    async function saveSettings(formId) {
        console.log('saveSettings called with formId:', formId);
        
        const form = document.getElementById(formId);
        if (!form) {
            console.error('Form not found:', formId);
            return;
        }
        
        const formData = new FormData(form);
        const dataToSave = {};
        let formType = 'unknown';
        if (formId === 'general-settings-form') formType = 'general';
        if (formId === 'thingspeak-settings-form') formType = 'thingspeak';
        if (formId === 'alert-settings-form') formType = 'alerts';
        
        console.log('Form type:', formType);

        // Récupérer et valider les données
        for (let [key, value] of formData.entries()) {
            if (key === 'email_alerts') {
                dataToSave[key] = value === 'true';
            } else if (!isNaN(parseFloat(value)) && isFinite(value)) {
                dataToSave[key] = parseFloat(value);
            } else {
                dataToSave[key] = value;
            }
        }

        // Validation
        const validation = validateSettings(dataToSave, formType);
        
        if (validation.errors.length > 0) {
            notificationService.error('Erreur de validation: ' + validation.errors.join(', '));
            return;
        }

        // Afficher les avertissements s'il y en a
        if (validation.warnings.length > 0) {
            const warningMessage = 'Avertissements :\n' + validation.warnings.join('\n') + '\n\nContinuer l\'enregistrement ?';
            const proceed = confirm(warningMessage);
            if (!proceed) return;
        }

        // Sauvegarder chaque paramètre
        const results = {};
        let hasError = false;

        for (const [key, value] of Object.entries(dataToSave)) {
            try {
                const response = await fetch(`${basePath}api/settings/${key}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ value: value }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();
                results[key] = { success: true, value: value };
                console.log(`Setting ${key} saved:`, result);

            } catch (error) {
                console.error(`Error saving setting ${key}:`, error);
                results[key] = { success: false, error: error.message };
                hasError = true;
            }
        }

        // Afficher le résultat
        if (hasError) {
            notificationService.error('Erreur lors de l\'enregistrement de certains paramètres');
        } else {
            notificationService.success('Paramètres enregistrés avec succès');
        }


    }

    // Écouteurs d'événements pour les formulaires
    // Gérer les soumissions de formulaires
    const generalSettingsForm = document.getElementById('general-settings-form');
    if (generalSettingsForm) {
        generalSettingsForm.addEventListener('submit', function(event) {
            event.preventDefault();
            console.log('General settings form submitted');
            saveSettings('general-settings-form');
        });
    }
    
    const thingspeakSettingsForm = document.getElementById('thingspeak-settings-form');
    if (thingspeakSettingsForm) {
        thingspeakSettingsForm.addEventListener('submit', function(event) {
            event.preventDefault();
            console.log('ThingSpeak settings form submitted');
            saveSettings('thingspeak-settings-form');
        });
    }

    const alertSettingsForm = document.getElementById('alert-settings-form');
    if (alertSettingsForm) {
        alertSettingsForm.addEventListener('submit', function(event) {
            event.preventDefault();
            console.log('Alert settings form submitted');
            saveSettings('alert-settings-form');
        });
    }



    // Gérer l'activation/désactivation de tous les champs d'alerte
    const emailAlertsCheckbox = document.getElementById('email_alerts');
    const alertSettingsContent = document.getElementById('alert-settings-content');
    
    if (emailAlertsCheckbox && alertSettingsContent) {
        function toggleAlertSettings() {
            if (emailAlertsCheckbox.checked) {
                alertSettingsContent.classList.remove('opacity-50', 'pointer-events-none');
            } else {
                alertSettingsContent.classList.add('opacity-50', 'pointer-events-none');
            }
        }
        
        // Initialiser l'état
        toggleAlertSettings();
        
        // Écouter les changements
        emailAlertsCheckbox.addEventListener('change', toggleAlertSettings);
    }
});