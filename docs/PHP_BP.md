```markdown
# Bonnes pratiques PHP — Guide exhaustif

## 1. Architecture & Organisation
- Utiliser une architecture claire : MVC, Hexagonal, Clean Architecture.
- Organiser le code par domaines et non par types de fichiers.
- Respecter PSR-1, PSR-4 (autoload), PSR-12 (style).
- Utiliser Composer pour la gestion des dépendances.
- Séparer logique métier, présentation et infrastructure.
- Nommer les fichiers et classes de façon explicite.
- Préférer des namespaces cohérents et hiérarchiques.

## 2. Qualité du Code
- Écrire un code lisible, simple, cohérent.
- Limiter la complexité cyclomatique.
- Éviter les fonctions trop longues.
- Préférer des fonctions pures quand possible.
- Utiliser des interfaces pour découpler les implémentations.
- Préférer la composition à l’héritage.
- Utiliser `final` quand la classe n’est pas destinée à être étendue.
- Favoriser la programmation orientée objet moderne.

## 3. Typage & Validation
- Activer les types stricts : `declare(strict_types=1);`
- Taper systématiquement : propriétés, paramètres, retours.
- Éviter les types mixtes (`mixed`) sauf cas extrêmes.
- Valider les entrées utilisateurs via un layer dédié.
- Éviter la magie PHP (variables variables, `__get` non contrôlé).

## 4. Sécurité
- Toujours utiliser des requêtes préparées (PDO, mysqli).
- Ne jamais concaténer des entrées utilisateur dans des requêtes SQL.
- Nettoyer et valider toute donnée externe.
- Protéger les sessions (cookies HttpOnly, Secure, SameSite).
- Éviter `eval()`, `exec()`, `shell_exec()`.
- Utiliser des mots de passe hachés (`password_hash`, `password_verify`).
- Implémenter CSRF tokens sur les formulaires.
- Désactiver `display_errors` en production.
- Gérer correctement les uploads (taille, type MIME, renommage).
- Désactiver les fonctions dangereuses dans `php.ini` en prod.

## 5. Erreurs & Exceptions
- Utiliser les exceptions plutôt que les retours de statut.
- Créer une hiérarchie propre d’exceptions métier.
- Ne pas laisser échapper des messages d’erreur internes en prod.
- Implémenter un global error handler + logger.
- Utiliser un niveau de logs adapté (info/debug/warning/error/critical).

## 6. Performances
- Activer l’OPcache.
- Éviter les boucles inutiles et les requêtes répétitives.
- Utiliser un cache (APCu, Redis).
- Minimiser les accès disque.
- Préférer des structures de données optimisées.
- Ne charger que les dépendances nécessaires (lazy loading).
- Éviter les ORM mal optimisés ou les requêtes N+1.

## 7. Tests & CI/CD
- Écrire des tests unitaires (PHPUnit, Pest).
- Écrire des tests d’intégration.
- Couvrir les cas limites et les scénarios critiques.
- Automatiser les tests dans un pipeline CI.
- Utiliser des environnements de staging.
- Mettre en place un lint automatique (PHPStan, Psalm).

## 8. Documentation
- Rédiger une documentation claire dans le code (PHPDoc minimaliste).
- Documenter le domaine métier en dehors du code.
- Documenter les API (OpenAPI, JSON Schema).
- Maintenir le README à jour.
- Commenter le “pourquoi” plutôt que le “comment”.

## 9. Gestion des dépendances
- Verrouiller les versions (`composer.lock`).
- Éviter les libs obsolètes ou trop lourdes.
- Supprimer régulièrement les dépendances non utilisées.
- Ne jamais versionner le dossier `vendor/`.

## 10. Séparation Environnements
- Aucune donnée sensible dans le code source.
- Utiliser des `.env` + configuration par environnement.
- Bloquer les erreurs détaillées, outils debug et dumping en prod.
- Utiliser des clés différentes pour dev/staging/prod.

## 11. API & Backends
- Utiliser JSON comme format standard.
- Respecter REST ou GraphQL selon besoin.
- Renvoyer des codes HTTP adaptés.
- Implémenter rate-limit, pagination, filtres.
- Versionner l’API.
- Sécuriser via OAuth2/JWT si nécessaire.

## 12. Bonnes pratiques spécifiques PHP
- Utiliser PDO plutôt que mysqli seul.
- Préférer des objets immuables quand possible.
- Utiliser `DateTimeImmutable`.
- Toujours vérifier les retours des fonctions natives.
- Utiliser les enums plutôt que des constantes magiques.
- Préférer `foreach` à `for` pour les tableaux.
- Utiliser `json_throw_on_error`.
- Ne jamais mélanger PHP logique et HTML directement.
- Utiliser un moteur de template simple (Twig, Plates, Latte).
- Laisser le contrôleur préparer les données, le template afficher.

## 13. Conventions de Style
- CamelCase pour les méthodes et propriétés.
- PascalCase pour les classes.
- CONSTANTES en MAJUSCULES.
- Indentation cohérente (4 espaces).
- Longueur de ligne raisonnable.

## 14. Sécurité des fichiers & serveurs
- Interdire l’accès aux répertoires internes via `.htaccess`.
- Empêcher l’exécution de PHP dans les dossiers upload.
- Utiliser HTTPS strictement.
- Configurer correctement CORS.
- Mettre régulièrement à jour PHP et les extensions.

## 15. Déploiement
- Déploiement automatisé (CI/CD).
- Pas d’opérations manuelles en prod.
- Base de données versionnée (migrations).
- Rollback facile.
- Monitoring en continu.

## 16. Outils recommandés
- Composer
- PHPStan / Psalm
- PHP-CS-Fixer / PHPCS
- PHPUnit / Pest
- Symfony VarDumper
- Docker pour isoler l’environnement
- Monolog pour la journalisation

## 17. Anti-patterns à éviter
- Code spaghetti.
- Utiliser `include`/`require` partout au lieu de l’autoload.
- Mélanger HTML + PHP dans les contrôleurs.
- Tout mettre en static.
- God objects.
- SQL dans les vues.
- Utiliser des globales.
- Copier-coller du code métier dans plusieurs fichiers.

## 18. Sécurité avancée
- Limiter les permissions sur fichiers/dirs.
- Désactiver `allow_url_fopen`.
- Filtrage strict sur les headers HTTP.
- Mettre en place un WAF si nécessaire.
- Audit régulier du code.

## 19. Évolutivité
- Penser dès la conception à la montée en charge.
- Prévoir le sharding ou réplication si nécessaire.
- Découplage strict du code métier.
- Utilisation d’événements (event-driven).

## 20. Maintenance long terme
- Conserver un changelog.
- Gérer les dépréciations à chaque release PHP.
- Nettoyer régulièrement le code.
- Simplifier au maximum les workflows internes.

## 21. Chemins et URLs (Ajout)
- Ne jamais coder en dur des chemins absolus dans l’application.
- Ne jamais référencer les noms de dossiers internes dans le code.
- Utiliser une configuration centrale pour déterminer le chemin racine.
- Toujours générer des URLs relatives ou via un routeur.
- Laisser les chemins serveur déterminés dynamiquement (`BASE_PATH`, autoload, bootstrap).


```
