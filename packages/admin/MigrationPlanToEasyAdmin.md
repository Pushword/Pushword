```
make a migration plan to go from sonata admin to
https://github.com/EasyCorp/EasyAdminBundle
```

# Plan de migration de Sonata Admin vers EasyAdminBundle

## Vue d'ensemble

Migration complète de Sonata Admin Bundle vers EasyAdminBundle 4.x pour le projet Pushword. Cette migration touchera:

- 8 classes Admin principales (PageAdmin, UserAdmin, MediaAdmin, ConversationAdmin, etc.)
- Système de FormFields personnalisé (AdminFormFieldManager)
- Contrôleurs CRUD personnalisés
- Templates Twig
- Événements et listeners
- Configuration et routes
- Tests

## Etape 0 : Préparation

- Créer et basculer sur une nouvelle branche pour la migration easyadmin-migration

## Étape 1: Installation et configuration initiale

### 1.1 Dépendances

- Ajouter `easycorp/easyadmin-bundle` dans `composer.json` (racine)
- Retirer `sonata-project/admin-bundle` et `sonata-project/doctrine-orm-admin-bundle` après migration
- Mettre à jour `packages/admin/composer.json`

### 1.2 Configuration EasyAdmin

- Créer `packages/admin/src/config/packages/easy_admin.php` pour remplacer `sonata_admin.php`
- Configurer le dashboard, les menus, la sécurité
- Configurer les routes dans `packages/admin/src/AdminRoutes.yaml`

### 1.3 Bundle registration

- Mettre à jour `packages/skeleton/config/bundles.php` pour remplacer Sonata par EasyAdmin

## Étape 2: Migration des classes Admin

### 2.1 AbstractAdmin de base

**Fichier:** `packages/admin/src/AbstractAdmin.php`

- Remplacer `SonataAbstractAdmin` par `AbstractCrudController` d'EasyAdmin
- Adapter l'interface `AdminInterface` si nécessaire
- Conserver `EntityManagerInterface` injection

### 2.2 PageAbstractAdmin

**Fichier:** `packages/admin/src/PageAbstractAdmin.php`

- Convertir en `AbstractCrudController` avec méthodes EasyAdmin:
- `configureCrud()` au lieu de `configure()`
- `configureFields()` au lieu de `configureFormFields()`
- `configureFilters()` au lieu de `configureDatagridFilters()`
- Adapter `configureQuery()` pour EasyAdmin (utiliser `createIndexQueryBuilder()`)
- Convertir les filtres personnalisés (CallbackFilter → FilterInterface)
- Adapter `getObjectMetadata()` pour EasyAdmin (utiliser `configureActions()`)

### 2.3 PageAdmin, PageRedirectionAdmin, PageCheatSheetAdmin

**Fichiers:** `packages/admin/src/PageAdmin.php`, `PageRedirectionAdmin.php`, `PageCheatSheetAdmin.php`

- Convertir en contrôleurs EasyAdmin séparés
- Hériter de `PageAbstractAdmin` adapté

### 2.4 UserAdmin

**Fichier:** `packages/admin/src/UserAdmin.php`

- Convertir en `AbstractCrudController`
- Adapter les champs de formulaire
- Convertir les filtres et listes

### 2.5 MediaAdmin

**Fichier:** `packages/admin/src/MediaAdmin.php`

- Convertir en `AbstractCrudController`
- Adapter le mode "mosaic" (utiliser `configureAssets()` et templates personnalisés)
- Convertir les filtres personnalisés

### 2.6 ConversationAdmin

**Fichier:** `packages/conversation/src/Admin/ConversationAdmin.php`

- Convertir en `AbstractCrudController`
- Adapter les champs de formulaire

## Étape 3: Migration du système AdminFormFieldManager

### 3.1 AdminFormFieldManager

**Fichier:** `packages/admin/src/AdminFormFieldManager.php`

- Adapter pour utiliser `FieldConfigurator` d'EasyAdmin au lieu de `FormMapper`
- Convertir la méthode `addFormField()` pour EasyAdmin
- Adapter `getFormFields()` pour retourner des configurations EasyAdmin

### 3.2 FormFields personnalisés

**Répertoire:** `packages/admin/src/FormField/`

- Convertir les 40+ classes FormField pour EasyAdmin
- Remplacer `FormMapper` par des méthodes retournant des configurations de champs EasyAdmin
- Adapter `AbstractField::formField()` pour retourner `FieldDto` ou configuration de champ

## Étape 4: Migration des contrôleurs CRUD

### 4.1 PageCRUDController

**Fichier:** `packages/admin/src/Controller/PageCRUDController.php`

- Remplacer `SonataCRUDController` par `AbstractCrudController` d'EasyAdmin
- Adapter `list()` pour utiliser `index()` d'EasyAdmin
- Adapter `tree()` pour utiliser une action personnalisée EasyAdmin
- Convertir `redirectTo()` pour EasyAdmin

### 4.2 Autres contrôleurs

- Adapter `PageCheatSheetController` et `MarkdownCheatsheetController` si nécessaire

## Étape 5: Migration des templates Twig

### 5.1 Layout principal

**Fichier:** `packages/admin/src/templates/layout.html.twig`

- Remplacer `@SonataAdmin/standard_layout.html.twig` par le layout EasyAdmin
- Adapter la structure HTML et les blocs

### 5.2 Templates CRUD

**Répertoire:** `packages/admin/src/templates/`

- `page/page_edit.html.twig` → adapter pour EasyAdmin
- `page/page_show.html.twig` → adapter pour EasyAdmin
- `page/page_list_titleField.html.twig` → convertir en FieldTemplate EasyAdmin
- `CRUD/mosaic.html.twig` → adapter pour EasyAdmin (utiliser `configureAssets()`)
- `Menu/menu.html.twig` → adapter pour le menu EasyAdmin
- Tous les autres templates qui étendent Sonata

### 5.3 Templates dans autres bundles

- `packages/page-scanner/src/templates/results.html.twig`
- `packages/static-generator/src/templates/*.html.twig`
- `packages/version/src/templates/list.html.twig`
- `packages/template-editor/src/templates/*.html.twig`

## Étape 6: Migration des événements et listeners

### 6.1 Événements Sonata → EasyAdmin

- `PersistenceEvent` → utiliser les événements EasyAdmin (`BeforeEntityPersistedEvent`, `AfterEntityPersistedEvent`, etc.)
- Adapter `packages/advanced-main-image/src/EventSuscriber/AdminFormEventSuscriber.php`
- Adapter `packages/admin-block-editor/src/EventSubscriber/AdminFormEventSubscriber.php`

### 6.2 Menu events

**Fichier:** `packages/admin/src/Menu/PageMenuProvider.php`

- Remplacer `ConfigureMenuEvent` par le système de menu EasyAdmin
- Utiliser `MenuBuilderEvent` d'EasyAdmin ou configurer via YAML/PHP

## Étape 7: Migration de la configuration

### 7.1 Configuration Sonata → EasyAdmin

**Fichiers de configuration:**

- `packages/admin/src/config/packages/sonata_admin.php` → `easy_admin.php`
- `packages/conversation/src/Resources/config/packages/sonata_admin.php`
- `packages/page-scanner/src/config/packages/sonata_admin.php`
- `packages/static-generator/src/config/packages/sonata_admin.php`
- `packages/template-editor/src/config/packages/sonata_admin.php`
- `packages/admin-block-editor/src/config/packages/sonata_admin.php`

### 7.2 Routes

**Fichier:** `packages/admin/src/AdminRoutes.yaml`

- Remplacer les routes Sonata par les routes EasyAdmin
- Adapter les préfixes et patterns

## Étape 8: Migration des tests

### 8.1 AbstractAdminTestClass

**Fichier:** `packages/admin/tests/AbstractAdminTestClass.php`

- Adapter pour EasyAdmin (changer les sélecteurs, URLs, etc.)
- Mettre à jour tous les tests qui utilisent cette classe

### 8.2 Tests spécifiques

- Adapter les tests dans `packages/admin/tests/`
- Adapter les tests dans les autres bundles (conversation, version, etc.)

## Étape 9: Nettoyage et dépendances

### 9.1 Suppression Sonata

- Retirer les bundles Sonata de `bundles.php`
- Retirer les dépendances Composer
- Nettoyer les imports inutilisés

### 9.2 Documentation

- Mettre à jour `packages/docs/content/extension/admin.md`
- Mettre à jour les références à Sonata dans la documentation

## Étape 10: Tests et validation

### 10.1 Tests fonctionnels

- Vérifier toutes les fonctionnalités CRUD
- Vérifier les filtres et recherches
- Vérifier les formulaires personnalisés
- Vérifier les templates et rendus

### 10.2 Tests d'intégration

- Tester le menu et la navigation
- Tester les événements et listeners
- Tester les permissions et sécurité

## Points d'attention

1. **FormFields personnalisés**: Le système `AdminFormFieldManager` est complexe et devra être entièrement réécrit pour EasyAdmin
2. **Modes de liste**: Le mode "mosaic" et "tree" devront être recréés avec EasyAdmin
3. **Filtres personnalisés**: Les `CallbackFilter` devront être convertis en `FilterInterface` EasyAdmin
4. **Templates**: Beaucoup de templates personnalisés à adapter
5. **Événements**: Le système d'événements est différent entre Sonata et EasyAdmin
6. **Compatibilité**: S'assurer que PHP 8.4+ et Symfony 7.3+ sont compatibles avec EasyAdmin 4.x

## Ordre de migration recommandé

1. Installation et configuration de base (Étape 1)
2. Migration d'une classe Admin simple (UserAdmin) pour valider l'approche (Étape 2.4)
3. Migration du système FormField (Étape 3)
4. Migration des autres classes Admin (Étape 2)
5. Migration des contrôleurs et templates (Étapes 4-5)
6. Migration des événements (Étape 6)
7. Migration de la configuration (Étape 7)
8. Migration des tests (Étape 8)
9. Nettoyage (Étape 9)
10. Validation finale (Étape 10)
