# Pushword - Technical Roadmap & Improvement Plan

This document outlines technical debt, security concerns, and improvement opportunities identified through codebase analysis.

> **Legend:** 🔴 Critical | 🟠 High | 🟡 Medium | 🟢 Low

---

## 🔒 Security Issues

### 🔴 Critical: Command Injection Vulnerabilities

#### Issue 1: Unsanitized Input in Shell Command

- **Location:** `packages/core/src/Service/ImageManager.php:76`
- **Problem:** Media filename is directly concatenated into shell command without sanitization
- **Risk:** If an attacker uploads a file with a malicious name like `; rm -rf /`, arbitrary commands could be executed

```php
exec('cd ../ && php bin/console pw:image:optimize '.$media->getFileName().' > /dev/null 2>/dev/null &');
```

- **Recommendation:**
  ```php
  exec('cd ../ && php bin/console pw:image:optimize '.escapeshellarg($media->getFileName()).' > /dev/null 2>/dev/null &');
  ```

#### Issue 2: Unsanitized Exec in PageScanner

- **Location:** `packages/page-scanner/src/Controller/PageScannerController.php:86`
- **Problem:** Direct exec() call without any input sanitization
- **Recommendation:** Use Symfony Process component instead of raw exec():
  ```php
  use Symfony\Component\Process\Process;
  $process = new Process(['php', 'bin/console', 'pw:page-scan']);
  $process->start();
  ```

---

### 🟠 High: SQL Injection Risk with addslashes

- **Location:** `packages/conversation/src/Repository/MessageRepository.php:55`
- **Problem:** Using `addslashes()` for LIKE query escaping instead of proper parameter binding
- **Risk:** Potential SQL injection if special characters bypass addslashes

```php
$tagEscaped = '%"'.addslashes($tag).'"%';
$queryBuilder->setParameter('tag'.$i, $tagEscaped);
```

- **Recommendation:** The LIKE clause should escape `%` and `_` wildcards properly:
  ```php
  $tagEscaped = '%"'.addcslashes($tag, '%_"\\').'"%';
  ```
  Or better, use Doctrine's dedicated escaping.

---

## 🏗️ Code Quality & Structure

### 🟠 High: Code Duplication in CrudControllers

#### Duplicated Methods: `normalizeBlock` & `filterFieldClassList`

- **Locations:**
  - `packages/admin/src/Controller/PageCrudController.php:248-282`
  - `packages/admin/src/Controller/MediaCrudController.php:170-208`
  - `packages/admin/src/Controller/UserCrudController.php:99-137`

- **Problem:** Identical logic duplicated across 3 controllers (~40 lines each)
- **Recommendation:** Extract to `AbstractAdminCrudController` as protected methods:

```php
// In AbstractAdminCrudController.php
abstract class AbstractAdminCrudController
{
    protected function normalizeBlock(array|string $block): array { ... }
    protected function filterFieldClassList(array $values): array { ... }
}
```

---

### 🟠 High: JavaScript Modal Code Duplication

- **Locations:**
  - `packages/admin/src/Resources/assets/admin.mediaPicker.js`
  - `packages/admin/src/Resources/assets/admin.inlinePopup.js`

- **Problem:** Both files implement similar modal logic:
  - Modal element creation
  - Bootstrap Modal handling
  - Iframe src management
  - Message event listeners

- **Recommendation:** Create a shared modal utility module:

```javascript
// admin.modalUtils.js
export function createModal(id, options) { ... }
export function openModal(id, url, iframe) { ... }
export function closeModal(id) { ... }
```

---

### 🟡 Medium: Duplicated Repository Methods

#### `getAllTags` Method Duplication

- **Locations:**
  - `packages/core/src/Repository/PageRepository.php:337-356`
  - `packages/conversation/src/Repository/MessageRepository.php:88-103`

- **Problem:** Nearly identical implementation for fetching and flattening tags
- **Recommendation:** Create a trait or base repository method:

```php
trait TagsRepositoryTrait
{
    public function getAllTags(): array
    {
        // Shared implementation
    }
}
```

---

### 🟡 Medium: Overly Complex Functions

#### `PageImporter::editPage()`

- **Location:** `packages/flat/src/Importer/PageImporter.php:181-238`
- **Problem:** 50+ lines handling multiple responsibilities:
  - Finding existing page
  - Property normalization
  - Date/time handling
  - Custom property setting
  - Host/locale initialization
- **Recommendation:** Extract into smaller methods:
  - `findOrCreatePage()`
  - `updatePageProperties()`
  - `initializeNewPageDefaults()`

#### `CustomPropertiesTrait::__call()`

- **Location:** `packages/core/src/Entity/SharedTrait/CustomPropertiesTrait.php:221-246`
- **Problem:** Magic method with complex conditional logic
- **Recommendation:** Simplify by documenting expected dynamic properties or using explicit getters

---

### 🟡 Medium: Typo in Method Name

- **Location:** `packages/core/src/Controller/PageController.php:226`
- **Problem:** Method name `noramlizeSlug` contains typo (should be `normalizeSlug`)
- **Recommendation:** Rename to `normalizeSlug`

```php
// Before
private function noramlizeSlug(?string $slug): string

// After
private function normalizeSlug(?string $slug): string
```

---

### 🟢 Low: Incomplete TODO Comments

Multiple unresolved TODOs indicate incomplete implementations:

| Location                                                            | TODO                          |
| ------------------------------------------------------------------- | ----------------------------- |
| `packages/core/src/Entity/Page.php:155`                             | Fix admin exception on submit |
| `packages/core/src/Controller/PageController.php:135`               | Move locale handling to event |
| `packages/core/src/Entity/Media.php:89`                             | Drop Page from mainImagePages |
| `packages/static-generator/src/Generator/ErrorPageGenerator.php:27` | Make useful with htaccess     |

---

## ⚡ Performance Issues

### 🟠 High: N+1 Query Pattern

#### In-Memory Filtering Instead of Database Query

- **Location:** `packages/flat/src/Importer/PageImporter.php:368-372`
- **Problem:** Loads all pages then filters in PHP:

```php
$pages = array_filter($this->getPages(), static fn (Page $page): bool => $page->getSlug() === $criteria);
```

- **Recommendation:** Use repository query with WHERE clause:

```php
return $this->pageRepo->findOneBy(['slug' => $criteria, 'host' => $host]);
```

---

### 🟠 High: Inefficient LIKE Queries on Text Columns

- **Location:** `packages/core/src/Repository/PageRepository.php:182-210`
- **Problem:** Multiple LIKE queries on `mainContent` column for media search
- **Recommendation:**
  1. Add a `media_usage` junction table to track which media are used in which pages
  2. Or implement full-text search indexing

---

### 🟡 Medium: Kernel Re-initialization in Page Scanner

- **Location:** `packages/page-scanner/src/Scanner/PageScannerService.php:66-82`
- **Problem:** Full kernel handle for each page scan causes overhead
- **Recommendation:** Use Twig rendering directly instead of simulating HTTP requests

```php
// Instead of
$response = self::getKernel()->handle($request);

// Consider
$html = $this->twig->render($page->getTemplate(), ['page' => $page]);
```

---

### 🟡 Medium: Tags Processing in PHP Instead of SQL

- **Location:** `packages/core/src/Repository/PageRepository.php:337-356`
- **Problem:** Fetches all tags then processes in PHP with array_merge/array_unique
- **Recommendation:** Consider JSON column with database-level distinct extraction if tags are stored as JSON

---

## 🧪 Testing & Maintainability

### 🟠 High: Excessive Test Skipping

- **Locations:** `packages/admin/tests/Frontend/AdminJSTest.php` (17 skips), `packages/static-generator/tests/Generator/CompressorTest.php` (6 skips)
- **Problem:** 23 `markTestSkipped()` calls reduce test coverage confidence
- **Recommendation:**
  1. Add proper feature detection instead of skipping
  2. Use mocks for browser-dependent tests
  3. Make compressor tests work with fallback implementations

---

### 🟡 Medium: Missing Test Coverage

Packages with limited or no tests:

- `packages/advanced-main-image/tests/` - No tests directory
- `packages/template-editor/tests/` - Only 2 tests
- `packages/page-update-notifier/tests/` - Only 1 test

**Recommendation:** Add at minimum:

- Unit tests for entity methods
- Integration tests for controllers
- Functional tests for key features

---

### 🟡 Medium: Tight Coupling with Filesystem

- **Problem:** Direct `new Filesystem()` instantiation in 15+ locations
- **Locations:** Multiple files in core, flat, static-generator packages
- **Recommendation:** Inject Filesystem as a service:

```php
public function __construct(
    private readonly Filesystem $filesystem,
) {}
```

---

## 📝 Best Practices

### 🟡 Medium: Missing Type Declarations

- **Location:** `packages/core/src/Entity/Media.php:82-84`
- **Problem:** Property `$mediaFile` uses union type in docblock but not in code

```php
/**
 * @var UploadedFile|File|null
 */
protected $mediaFile;
```

- **Recommendation:** Add proper union type:

```php
protected UploadedFile|File|null $mediaFile = null;
```

---

### 🟡 Medium: SOLID Violations

#### Single Responsibility Principle (SRP)

- **Location:** `packages/core/src/Controller/PageController.php:116-140`
- **Problem:** `show()` method handles:
  - Host initialization
  - Page retrieval
  - Canonical URL checking
  - Redirection handling
  - Locale setting
  - Template rendering
- **Recommendation:** Extract concerns into dedicated services/event subscribers

#### Dependency Inversion Principle (DIP)

- **Problem:** Direct instantiation of concrete classes (Filesystem, Slugify)
- **Recommendation:** Inject interfaces/abstractions

---

### 🟢 Low: Documentation Gaps

Files lacking adequate documentation:

- `packages/core/src/Component/EntityFilter/Manager.php`
- `packages/static-generator/src/Generator/` classes
- Most Twig extensions

**Recommendation:** Add PHPDoc blocks with:

- Class purpose
- Method descriptions
- Parameter/return types
- Usage examples

---

## 📋 Prioritized Action Items

### Phase 1: Critical Security (Week 1) ✅ COMPLETED

- [x] Fix command injection in ImageManager - **FIXED**: Using Symfony Process instead of exec()
- [x] Replace exec() with Symfony Process in PageScannerController - **FIXED**
- [x] Fix addslashes SQL escaping in MessageRepository - **FIXED**: Using proper escapeLikePattern()

### Phase 2: High Priority Refactoring (Weeks 2-3) ✅ COMPLETED

- [x] Extract shared CrudController methods - **FIXED**: normalizeBlock/filterFieldClassList in AbstractAdminCrudController
- [x] Create shared JS modal utility - **FIXED**: Created admin.modalUtils.js
- [x] Fix N+1 query in PageImporter - **FIXED**: Using indexed lookup with getPagesIndexedBySlug()
- [x] Optimize LIKE queries in PageRepository - **FIXED**: Simplified and limited query

### Phase 3: Code Quality (Weeks 4-6)

- [ ] Fix typos (normalizeSlug)
- [ ] Remove code duplication in repositories
- [ ] Simplify complex functions
- [ ] Address TODO comments

### Phase 4: Testing Improvement (Ongoing)

- [ ] Reduce markTestSkipped usage
- [ ] Add tests for untested packages
- [ ] Increase coverage to 70%+

### Phase 5: Performance Optimization (Ongoing)

- [ ] Refactor page scanner for direct rendering
- [ ] Add database indexes if missing

---

## 📊 Metrics to Track

| Metric                  | Current  | Target  | Status |
| ----------------------- | -------- | ------- | ------ |
| PHPStan Level           | 5        | Level 8 | 🟡     |
| Test Coverage           | ~40%     | 70%+    | 🟡     |
| Skipped Tests           | 23       | < 5     | 🟡     |
| TODO Comments           | 10       | 0       | 🟡     |
| exec() without escaping | ~~2~~ 0  | 0       | ✅     |
| Duplicated code blocks  | ~~5+~~ 2 | 0       | 🟡     |

---

## 📝 Changes Log

### 2024-11-27 - Security & Refactoring Sprint

- **ImageManager.php**: Replaced unsafe `exec()` with Symfony Process for background image optimization
- **PageScannerController.php**: Replaced unsafe `exec()` with Symfony Process for page scanning
- **AbstractAdminCrudController.php**: Extracted `normalizeBlock()` and `filterFieldClassList()` methods
- **MediaCrudController.php**: Removed duplicated methods (now inherited)
- **UserCrudController.php**: Removed duplicated methods (now inherited)
- **PageCrudController.php**: Removed duplicated methods, updated to use parent's `normalizeBlock()`
- **admin.modalUtils.js**: New shared modal utility module
- **admin.mediaPicker.js**: Refactored to use shared modal utilities
- **admin.inlinePopup.js**: Refactored to use shared modal utilities
- **MessageRepository.php**: Fixed SQL injection risk with proper LIKE pattern escaping
- **PageImporter.php**: Fixed N+1 query pattern with indexed page lookup
- **PageRepository.php**: Simplified and optimized media search query

---

_Last updated: 2024-11-27_
_Generated by automated codebase analysis_

---

# Refactoring : Rendu sans simulation de requête HTTP

## Analyse du problème

### Situation actuelle

Le `PageScanner` et `StaticGenerator` simulent des requêtes HTTP :

```php
$request = Request::create($liveUri);
$response = self::getKernel()->handle($request);
```

**Temps observé** : 26.84s pour le test PageScanner.

### Pourquoi c'est lent ?

`kernel->handle()` fait beaucoup de travail même si le kernel est réutilisé :

- Dispatch `kernel.request`, `kernel.controller`, `kernel.response`
- Routing complet
- Security checks
- Redirections SEO
- Locale resolution

---

## Complexités identifiées

### 1. Services avec état au constructeur (CRITIQUE)

**`PushwordRouteGenerator`** :

```php
public function __construct(..., RequestStack $requestStack) {
    $this->currentHost = $requestStack->getCurrentRequest()?->getHost() ?? '';
}
```

Ce singleton garde le host de la première requête. Sans simulation HTTP, il sera incorrect.

### 2. Filtre `HtmlLinkMultisite` (CRITIQUE)

Dépend de `$this->router->mayUseCustomPath()` qui utilise `$currentHost`.

### 3. Twig inline dans le contenu (CRITIQUE)

Le filtre `Twig` rend du code inline :

```php
$templateWrapper = $this->twig->createTemplate($string);
return $templateWrapper->render(['page' => $this->page]);
```

Ce code peut utiliser `app.request` ou `pages_list()` qui dépend de `RequestStack`.

### 4. Pager et route name (MOYEN)

`PageExtension` lit `requestStack->getCurrentRequest()->attributes`.

---

## Approches proposées

### Option A : RequestStack push (RECOMMANDÉE)

Pousser une requête dans `RequestStack` sans passer par `kernel->handle()` :

```php
public function renderPage(Page $page, int $pager = 1): string
{
    $request = Request::create($this->generateLivePathFor($page));
    $request->attributes->set('pager', $pager);
    $request->attributes->set('_route', $this->getRouteName($page));

    $this->requestStack->push($request);
    try {
        $this->apps->switchCurrentApp($page);
        $this->apps->setCurrentPage($page);
        $this->translator->setLocale($page->getLocale());

        $params = ['page' => $page, ...$this->apps->get()->getParamsForRendering()];
        $view = $this->apps->get()->getView($page->getTemplate() ?? '/page/page.html.twig');
        return $this->twig->render($view, $params);
    } finally {
        $this->requestStack->pop();
    }
}
```

**Prérequis** : Modifier `PushwordRouteGenerator` pour lire le host dynamiquement :

```php
private function getCurrentHost(): string
{
    // Priorité 1: Page courante
    if (null !== ($page = $this->apps->getCurrentPage())) {
        return $page->getHost();
    }
    // Priorité 2: Requête courante
    return $this->requestStack->getCurrentRequest()?->getHost() ?? '';
}
```

**Avantages** :

- `app.request` fonctionne dans les templates
- Compatibilité 100%
- Gain ~50%

---

### Option B : RenderingContext

Créer un contexte explicite stocké dans `AppPool` :

```php
final class RenderingContext
{
    public function __construct(
        public readonly string $host,
        public readonly int $pager = 1,
        public readonly string $routeName = 'pushword_page',
    ) {}
}
```

Chaque service vérifie ce contexte en priorité sur `RequestStack`.

**Inconvénient** : `app.request` ne fonctionnera pas dans le Twig inline.

---

### Option C : Optimiser kernel->handle()

Garder la simulation mais créer un environnement `static` avec moins de listeners.

**Avantage** : Pas de risque de régression.

---

## Comparaison

| Critère                     | Option A  | Option B  | Option C |
| --------------------------- | --------- | --------- | -------- |
| Gain performance            | ~50%      | ~70%      | ~20%     |
| Compatibilité `app.request` | ✅ 100%   | ⚠️ 80%    | ✅ 100%  |
| Effort                      | 2-3 jours | 5-7 jours | 0.5 jour |
| Risque régression           | Moyen     | Élevé     | Faible   |

---

## Checklist Option A

1. [ ] Modifier `PushwordRouteGenerator` : lire host dynamiquement
2. [ ] Modifier `PageExtension::getCurrentPage()` : fallback sur `AppPool`
3. [ ] Modifier `PageExtension::getPagerRouteName()` : déduire depuis page
4. [ ] Modifier `page_default.html.twig` : `pager|default(0)`
5. [ ] Créer `PageRenderer` avec push/pop RequestStack
6. [ ] Refactorer `PageGenerator` et `PageScannerService`
7. [ ] Tests de comparaison HTML
