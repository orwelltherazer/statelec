# TODO - Améliorations Statelec

## Fonctionnalités à implémenter

### Module Linky
- [ ] Modifier le firmware/config du module Linky pour envoyer les timestamps en UTC au lieu de l'heure locale
  - Cela évitera la conversion côté API et permettra une vraie internationalisation
  - Actuellement, l'API convertit en supposant heure locale en entrée

### Internationalisation
- [ ] Tester l'app avec différents fuseaux horaires (ex: America/New_York, Asia/Tokyo)
- [ ] Vérifier que les calculs d'heures creuses/pleines s'adaptent au fuseau
- [ ] Ajouter une interface pour changer le TIMEZONE sans éditer .env

### Sécurité
- [ ] Générer une vraie API_KEY sécurisée (pas 'your_secure_api_key_here')
- [ ] Ajouter rate limiting sur l'API
- [ ] Valider les entrées API plus strictement

### Performance
- [ ] Ajouter cache pour les requêtes répétées (ex: settings)
- [ ] Optimiser les requêtes SQL avec index si nécessaire
- [ ] Minifier les JS/CSS pour prod

### Tests
- [ ] Ajouter tests unitaires pour les contrôleurs
- [ ] Tests d'intégration pour l'API
- [ ] Tests de charge pour les timestamps

### UI/UX
- [ ] Améliorer le responsive design
- [ ] Ajouter des animations de chargement
- [ ] Internationaliser les messages (i18n)

## Bugs connus
- [ ] Vérifier la gestion des heures d'été/hiver avec les nouveaux fuseaux

## Notes
- L'app stocke les timestamps en UTC, affiche en fuseau configuré
- Les calculs utilisent le fuseau configuré pour cohérence</content>
<parameter name="filePath">C:\xampp\htdocs\statelec\TODO.md