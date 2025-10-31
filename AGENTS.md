Pushword is a modular CMS built as a collection of Symfony bundles. This is a monorepo containing the core bundle and multiple extensions.

- **PHP** >= 8.4
- **Symfony** 7.3 (Framework principal)
- **Doctrine ORM** 3.0 (Gestion de base de données)
- **Twig** (Moteur de templates)
- **Node.js** + **Yarn** (Assets frontend)
- **Composer** (Gestion des dépendances PHP)

```
./
├── packages/
│ ├── skeleton/         # Debug and demo app (development environment)
│ ├── core/             # Core bundle with base entities and services
│ │ └── src/Entity/     # Core entities (Page, User, Media)
│ ├── docs/
│ │ └── content/        # Project documentation
│ └── [other-bundles]/  # Extension bundles (each with own src/ and tests/)
│ └── skeleton/         # Development/demo application
```

### Entities

- **Page** - packages/core/src/Entity/Page.php
- **User** - packages/core/src/Entity/User.php
- **Media** - packages/core/src/Entity/Media.php

## Useful commands

You can run this command from skeleton folder `cd packages/skeleton` :

```bash
# Lister toutes les commandes
php bin/console list pushword

# Vérifier la configuration
php bin/console debug:config pushword

# Voir les routes
php bin/console debug:router

# Vérifier les services
php bin/console debug:container
```

### Commandes d'extension

```bash
# Import/Export flat-file
php bin/console pushword:flat:import
php bin/console pushword:flat:export

# Gestion des images
php bin/console pushword:image:cache
php bin/console pushword:image:optimize

# Scanner de pages
php bin/console pushword:page:scan

# Génération statique
php bin/console pushword:static:generate
```

## Monorepo useful commands

```bash
# Tests
composer test
vendor/bin/phpunit --filter ...

# Formatage du code
composer format

# Analyse statique
composer stan

# Génération des assets
composer assets

# Documentation
composer docs
```

## Asset Management and Developpement server

- You don't need to generate assets, the developer runs yarn watch automatically
- You don't need to run the developpement server `composer dev`, the developer runs it automatically and it's accessible at `http://localhost:8001`
