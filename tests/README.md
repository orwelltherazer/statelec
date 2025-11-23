# Tests Statelec V2

Ce dossier contient les tests automatisés de l'application.

## Structure

```
tests/
├── Unit/           # Tests unitaires
├── Integration/    # Tests d'intégration
├── Feature/        # Tests fonctionnels
└── bootstrap.php   # Initialisation des tests
```

## Lancement des tests

```bash
# Tous les tests
vendor/bin/phpunit

# Tests unitaires uniquement
vendor/bin/phpunit tests/Unit

# Avec coverage
vendor/bin/phpunit --coverage-html coverage
```

## Technologies

- PHPUnit 9.x+
- Couverture minimale requise: 70%

## Conventions

- Un test par méthode publique
- Nom des tests: `test_methodName_scenario_expectedResult`
- Utiliser les data providers pour les cas multiples
- Mocker les dépendances externes

## À venir (Phase 11)

Les tests seront implémentés dans la Phase 11 du plan d'évolution.
