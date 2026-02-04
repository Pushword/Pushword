# Pushword CMS Refactoring Plan

## Overview

5 targeted improvements ordered by impact: security, then maintainability.
BC breaks are acceptable - all packages are in the monorepo, all callers updated in one pass.

---

## Phase 1: FilterWhereParser - DQL Operator Injection

**File:** `packages/core/src/Repository/FilterWhereParser.php:80-100`

The `$operator` parameter is concatenated directly into the DQL expression without validation:

```php
return $prefix.$key.' '.$operator.' '.$sqlValue;
```

**Changes:**

- Add `ALLOWED_OPERATORS` constant with explicit allowlist (`=`, `!=`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `IS`, `IS NOT`)
- Validate `$operator` against the allowlist in `retrieveExpressionFrom()`
- Validate `$key` and `$prefix` with regex (`/^[a-zA-Z_][a-zA-Z0-9_.]*$/`)
- Replace generic `throw new Exception(...)` with `throw new \InvalidArgumentException(...)`
- Add unit test `packages/core/tests/Repository/FilterWhereParserTest.php`

---

## Phase 2: Media Entity Decomposition

**File:** `packages/core/src/Entity/Media.php` (612 lines)

Three concerns beyond data storage:

| Lines   | Concern                                                                  | Extraction Target              |
| ------- | ------------------------------------------------------------------------ | ------------------------------ |
| 357-450 | Slug computation (changeSlug, setSlugForNewMedia, getMediaFromFilename)  | `MediaSlugManager` service     |
| 452-568 | Alt text YAML parsing (getAltsParsed, editLocalizedAlt, updateAltSearch) | `MediaAltManager` service      |
| 570-611 | Hash computation with filesystem access (setHash, sha1_file)             | Move to `MediaStorageListener` |

### Changes

**Create `packages/core/src/Service/MediaSlugManager.php`:**

- `computeSlug(Media $media, string $slug): string`
- `computeSlugForNewMedia(Media $media, string $filename): string`
- Called from `MediaStorageListener` during persist/update events

**Create `packages/core/src/Service/MediaAltManager.php`:**

- `parseAlts(string $altsYaml): array`
- `editLocalizedAlt(Media $media, string $locale, string $alt): void`
- `getAltByLocale(Media $media, string $locale): string`

**Move hash logic to `MediaStorageListener`:**

- Entity keeps `$hash` as simple stored property
- Filesystem access removed from entity

**Remove old methods from entity. Update all callers directly:**

- `flat/MediaExporter.php` (2 calls to `getAltsParsed()`) -> use `MediaAltManager`
- `flat/MediaSync.php`, `flat/MediaImporter.php` (call `getHash()`) -> hash stays as simple getter
- `core/MediaNamingListener.php`, `core/MediaHashListener.php`, `core/MediaStorageListener.php` (call `setHash()`) -> hash computation moves into these listeners
- `admin-block-editor/MediaBlockController.php` (call `getHash()`) -> simple getter stays
- `skeleton/AppFixtures.php` (call `setHash()`) -> call `setHash(sha1_file(...))` explicitly
- `flat/MediaExportImportTest.php`, `core/MediaRepositoryTest.php` -> update test calls

---

## Phase 3: Magic String Consolidation

### 3A. Redirection prefix

- Add `PageRedirection::PREFIX = 'Location:'`
- Use in `PageRedirection.php:17`, `PageRepository.php` (LIKE query), `PageCrudController.php:89`

### 3B. Default locale

- Add `PushwordDefaults::DEFAULT_LOCALE = 'en'` (new file `packages/core/src/PushwordDefaults.php`)
- Replace hardcoded `'en'` in `User.php:45`, `DateShortcodeParser.php:114`, `Date.php:84`

### 3C. Password minimum length

- Add `User::PASSWORD_MIN_LENGTH = 7`
- Reference in `#[Assert\Length(min: self::PASSWORD_MIN_LENGTH, ...)]`

### 3D. Role constants

- Add `User::ROLE_EDITOR = 'ROLE_EDITOR'` and `User::ROLE_ADMIN = 'ROLE_ADMIN'`
- Replace raw strings in `LinkProvider.php:32`, `PageResolver.php:41`, security config

---

## Phase 4: Page Entity Cleanup

### 4A. `setMainContent()` hidden side effects

**File:** `packages/core/src/Entity/Page.php:262-272`

The setter silently strips empty anchor tags via regex.

**Changes:**

- Extract `ContentCleaner::removeEmptyAnchors(string $content): string` to `packages/core/src/Utils/ContentCleaner.php`
- Move the regex call to a Doctrine `PrePersist`/`PreUpdate` listener (or into existing `PageListener`)
- Setter becomes: assign + trim + cache invalidation only

### 4B. `addTranslation()` bidirectional sync

**File:** `packages/core/src/Entity/PageTrait/PageI18nTrait.php:50-83`

Recursive bidirectional syncing is correct but hard to test without Doctrine.

**Changes:**

- Extract to `TranslationSynchronizer` service in `packages/core/src/Service/`
- Entity `addTranslation()` / `removeTranslation()` become simple collection operations (no recursive sync)
- Service handles graph synchronization (`synchronize(Page $page, Page $translation)`)
- Update all callers to use the service:
  - `flat/PageImporter.php:417,430` -> inject `TranslationSynchronizer`
  - `skeleton/AppFixtures.php:185-186` -> inject `TranslationSynchronizer`
  - `flat/PageSyncTest.php` (25+ calls) -> update test setup to use service

---

## Phase 5: Test Factories

After Phases 2 and 4 extract logic into services, those services become unit-testable.

**Create in `packages/core/tests/Factory/`:**

- `PageFactory::create(array $overrides = []): Page`
- `MediaFactory::create(array $overrides = []): Media`
- `UserFactory::create(array $overrides = []): User`

Simple builders with sensible defaults, no DB interaction.

---

## Execution Order

```
Phase 1 (Security)        ─┐
Phase 3 (Constants)        ─┼── can run in parallel
                            │
Phase 2 (Media extraction) ─┘
Phase 4 (Page cleanup)     ── independent
         │
         v
Phase 5 (Test factories)   ── depends on Phases 2 & 4
```

## Verification

After each phase:

1. `composer stan` - PHPStan must pass at max level
2. `composer rector` - Code style must pass
3. `composer test` - Full test suite must pass
4. `composer console cache:clear` - No container errors

# Pushword CMS Refactoring Plan

## Overview

5 targeted improvements ordered by impact: security, then maintainability.
BC breaks are acceptable - all packages are in the monorepo, all callers updated in one pass.

---

## Phase 1: FilterWhereParser - DQL Operator Injection

**File:** `packages/core/src/Repository/FilterWhereParser.php:80-100`

The `$operator` parameter is concatenated directly into the DQL expression without validation:

```php
return $prefix.$key.' '.$operator.' '.$sqlValue;
```

**Changes:**

- Add `ALLOWED_OPERATORS` constant with explicit allowlist (`=`, `!=`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `IS`, `IS NOT`)
- Validate `$operator` against the allowlist in `retrieveExpressionFrom()`
- Validate `$key` and `$prefix` with regex (`/^[a-zA-Z_][a-zA-Z0-9_.]*$/`)
- Replace generic `throw new Exception(...)` with `throw new \InvalidArgumentException(...)`
- Add unit test `packages/core/tests/Repository/FilterWhereParserTest.php`

---

## Phase 2: Media Entity Decomposition

**File:** `packages/core/src/Entity/Media.php` (612 lines)

Three concerns beyond data storage:

| Lines   | Concern                                                                  | Extraction Target              |
| ------- | ------------------------------------------------------------------------ | ------------------------------ |
| 357-450 | Slug computation (changeSlug, setSlugForNewMedia, getMediaFromFilename)  | `MediaSlugManager` service     |
| 452-568 | Alt text YAML parsing (getAltsParsed, editLocalizedAlt, updateAltSearch) | `MediaAltManager` service      |
| 570-611 | Hash computation with filesystem access (setHash, sha1_file)             | Move to `MediaStorageListener` |

### Changes

**Create `packages/core/src/Service/MediaSlugManager.php`:**

- `computeSlug(Media $media, string $slug): string`
- `computeSlugForNewMedia(Media $media, string $filename): string`
- Called from `MediaStorageListener` during persist/update events

**Create `packages/core/src/Service/MediaAltManager.php`:**

- `parseAlts(string $altsYaml): array`
- `editLocalizedAlt(Media $media, string $locale, string $alt): void`
- `getAltByLocale(Media $media, string $locale): string`

**Move hash logic to `MediaStorageListener`:**

- Entity keeps `$hash` as simple stored property
- Filesystem access removed from entity

**Remove old methods from entity. Update all callers directly:**

- `flat/MediaExporter.php` (2 calls to `getAltsParsed()`) -> use `MediaAltManager`
- `flat/MediaSync.php`, `flat/MediaImporter.php` (call `getHash()`) -> hash stays as simple getter
- `core/MediaNamingListener.php`, `core/MediaHashListener.php`, `core/MediaStorageListener.php` (call `setHash()`) -> hash computation moves into these listeners
- `admin-block-editor/MediaBlockController.php` (call `getHash()`) -> simple getter stays
- `skeleton/AppFixtures.php` (call `setHash()`) -> call `setHash(sha1_file(...))` explicitly
- `flat/MediaExportImportTest.php`, `core/MediaRepositoryTest.php` -> update test calls

---

## Phase 3: Magic String Consolidation

### 3A. Redirection prefix

- Add `PageRedirection::PREFIX = 'Location:'`
- Use in `PageRedirection.php:17`, `PageRepository.php` (LIKE query), `PageCrudController.php:89`

### 3B. Default locale

- Add `PushwordDefaults::DEFAULT_LOCALE = 'en'` (new file `packages/core/src/PushwordDefaults.php`)
- Replace hardcoded `'en'` in `User.php:45`, `DateShortcodeParser.php:114`, `Date.php:84`

### 3C. Password minimum length

- Add `User::PASSWORD_MIN_LENGTH = 7`
- Reference in `#[Assert\Length(min: self::PASSWORD_MIN_LENGTH, ...)]`

### 3D. Role constants

- Add `User::ROLE_EDITOR = 'ROLE_EDITOR'` and `User::ROLE_ADMIN = 'ROLE_ADMIN'`
- Replace raw strings in `LinkProvider.php:32`, `PageResolver.php:41`, security config

---

## Phase 4: Page Entity Cleanup

### 4A. `setMainContent()` hidden side effects

**File:** `packages/core/src/Entity/Page.php:262-272`

The setter silently strips empty anchor tags via regex.

**Changes:**

- Extract `ContentCleaner::removeEmptyAnchors(string $content): string` to `packages/core/src/Utils/ContentCleaner.php`
- Move the regex call to a Doctrine `PrePersist`/`PreUpdate` listener (or into existing `PageListener`)
- Setter becomes: assign + trim + cache invalidation only

### 4B. `addTranslation()` bidirectional sync

**File:** `packages/core/src/Entity/PageTrait/PageI18nTrait.php:50-83`

Recursive bidirectional syncing is correct but hard to test without Doctrine.

**Changes:**

- Extract to `TranslationSynchronizer` service in `packages/core/src/Service/`
- Entity `addTranslation()` / `removeTranslation()` become simple collection operations (no recursive sync)
- Service handles graph synchronization (`synchronize(Page $page, Page $translation)`)
- Update all callers to use the service:
  - `flat/PageImporter.php:417,430` -> inject `TranslationSynchronizer`
  - `skeleton/AppFixtures.php:185-186` -> inject `TranslationSynchronizer`
  - `flat/PageSyncTest.php` (25+ calls) -> update test setup to use service

---

## Phase 5: Test Factories

After Phases 2 and 4 extract logic into services, those services become unit-testable.

**Create in `packages/core/tests/Factory/`:**

- `PageFactory::create(array $overrides = []): Page`
- `MediaFactory::create(array $overrides = []): Media`
- `UserFactory::create(array $overrides = []): User`

Simple builders with sensible defaults, no DB interaction.

---

## Execution Order

```
Phase 1 (Security)        ─┐
Phase 3 (Constants)        ─┼── can run in parallel
                            │
Phase 2 (Media extraction) ─┘
Phase 4 (Page cleanup)     ── independent
         │
         v
Phase 5 (Test factories)   ── depends on Phases 2 & 4
```

## Verification

After each phase:

1. `composer stan` - PHPStan must pass at max level
2. `composer rector` - Code style must pass
3. `composer test` - Full test suite must pass
4. `composer console cache:clear` - No container errors
