# Guide pour les Agents IA - Pushword CMS

Ce document fournit des informations essentielles pour les agents IA travaillant sur le projet Pushword CMS.

## Vue d'ensemble du projet

**Pushword** est un CMS orienté pages construit comme un Bundle Symfony pour créer, gérer et maintenir rapidement des sites web.

Ce repository est un monorepo organisé en packages modulaires contenant le core de pushword et ses extensions.

### Technologies principales

- **PHP** >= 8.4
- **Symfony** 7.3 (Framework principal)
- **Doctrine ORM** 3.0 (Gestion de base de données)
- **Twig** (Moteur de templates)
- **Node.js** + **Yarn** (Assets frontend)
- **Composer** (Gestion des dépendances PHP)

## Architecture du projet

### Structure monorepo

Le projet est organisé en packages dans le dossier `/packages/` :

#### Packages principaux

- **`core/`** - Bundle principal contenant les fonctionnalités de base
- **`admin/`** - Interface d'administration basée sur Sonata Admin
- **`skeleton/`** - Application de démonstration et de test

#### Extensions officielles

- **`admin-block-editor/`** - Éditeur de blocs riche pour l'admin
- **`conversation/`** - Système de commentaires et formulaires de contact
- **`flat/`** - Transformation en CMS flat-file
- **`page-scanner/`** - Scanner de liens morts et erreurs
- **`page-update-notifier/`** - Notifications par email des modifications
- **`static-generator/`** - Génération de sites statiques
- **`template-editor/`** - Éditeur de templates en ligne
- **`version/`** - Système de versioning des pages
- **`advanced-main-image/`** - Gestion avancée des images principales

#### Documentation

La documentation est écrire dans le packages `packages/docs/content/`

Le fichier `upgrade.md` est le fichier pour faciliter les mises à jour.

### Entités principales

#### Page (Entité centrale)

- **Page** - packages/core/src/Entity/Page.php
- **User** - packages/core/src/Entity/User.php
- **Media** - packages/core/src/Entity/Media.php

## Commandes console importantes

### Commandes de base

```bash
# Créer un nouveau site
php bin/console pushword:new

# Créer un utilisateur admin
php bin/console pushword:user:create

# Lister toutes les commandes
php bin/console list pushword
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

Générer le front :

```bash
composer assets --all

cd packages/admin-block-editor-tools && yarn && yarn build
```

Tu n'as pas besoin de générer les assets, le développeur lance systématiquement les commandes yarn watch

## Configuration

### Variables d'environnement

- Configuration via `.env` (Symfony standard)
- Base de données SQLite par défaut
- Support multi-sites via configuration hosts (voir packages/skeleton/config/packages/pushword.yaml)

## Développement

### Scripts Composer utiles

```bash
# Tests
composer test

# Formatage du code
composer format

# Analyse statique
composer stan

# Génération des assets
composer assets

# Documentation
composer docs
```

### Structure de développement

- **Tests** : Chaque package a son dossier `tests/`
- **Standards** : PSR-12, PHPStan, PHPUnit
- **CI/CD** : GitHub Actions pour tests et déploiement de la doc

## Points d'attention pour les agents

### 1. Architecture modulaire

- Chaque extension est un Bundle Symfony indépendant
- Le core contient uniquement les fonctionnalités essentielles
- Les extensions sont optionnelles et peuvent être installées via Composer

## Commandes de débogage

```bash
# Vérifier la configuration
php bin/console debug:config pushword

# Voir les routes
php bin/console debug:router

# Vérifier les services
php bin/console debug:container

# Logs en temps réel
tail -f var/log/dev.log
```
