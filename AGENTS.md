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

### Compatibility & Security

- Ensure compatibility with **Symfony** 7.3+ and **PHP** 8.4+ versions
- Follow secure coding practices to prevent XSS, CSRF, injections, auth bypasses, etc.
- **DB** : SQLite (via Doctrine) - No migrations, use `bin/console doctrine:schema:update --force`

### Coding Standards & Tooling

- Use **PHPUnit** for unit and functional testing
- Use php-cs-fixer`composer format` to ensure consistent code style
- Use **PHPStan** `composer stan` for static analysis
- Use **CI** `composer test` to run all tests and checks automatically
- Use Tailwind CSS 4

## PHP Code

- Use modern PHP 8.4+ syntax and features
- Do not use deprecated features from PHP, Symfony, or Pushword
- Add type declarations for all properties, arguments, and return values
- Use `camelCase` for variables and method names
- Use `SCREAMING_SNAKE_CASE` for constants
- Use **fast returns** instead of nesting logic unnecessarily
- Use trailing commas in multi-line arrays and argument lists
- Use PHPDoc only when necessary (e.g. `@var Collection<ProductInterface>`)
- Group class elements in this order: constants, properties, constructor, public methods, protected methods, private methods
- Group getter and setter methods for the same properties together
- Suffix interfaces with Interface, traits with Trait
- indent with 4 spaces

## Twig Templates

- indent with 2 spaces
- i18n : use camelCase for translatable text and keep them organized in `packages/<package>/translations/messages.<locale>.yaml` in alphabetical order.

### Entities

- **Page** - packages/core/src/Entity/Page.php
- **User** - packages/core/src/Entity/User.php
- **Media** - packages/core/src/Entity/Media.php

## Useful commands

You can run this command from skeleton folder `cd packages/skeleton` :

```bash
# List all commands
php bin/console list pushword

# Configuration
php bin/console debug:config pushword

# Routes
php bin/console debug:router

# Services
php bin/console debug:container
```

## Monorepo useful commands

```bash
# Tests
composer test
composer test-filter ...

# Code formatting
composer rector

# PHP Static Analysis
composer stan

# Generate assets
composer assets

```

## Debugging

When a task impacts the admin UI or other frontend pieces, feel free to open the running site with the integrated browser to validate the behaviour and grab screenshots.

Use `symfony server:list` to see if a local server is already running, or start one with `composer dev`.

Default credentials are `admin@example.tld` / `p@ssword` (ROLE_SUPER_ADMIN). If that user fails, reset the demo via `composer reset`.

## Quality Gates

- Run linters `composer stan|rector` and tests `composer test-filter ...` or `composer test` and fix warnings; never leave broken builds.
- you must avoid to skip tests by using `self::markTestSkipped('...')` or `@group skip` in tests ➜ fix them !
- Clear cache after each modification `composer console cache:clear`
- Write comment and documentation in english only.
