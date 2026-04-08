# Page Cache Feature Plan

## Context

Pushword serves pages dynamically via Symfony. For most sites, this is fine, but sites with traffic would benefit from serving pre-rendered HTML directly from the web server (Caddy/FrankenPHP), bypassing PHP entirely for anonymous visitors. The existing `pw:static` command exports a full static site for deployment elsewhere — this feature is different: it pre-renders pages into `public/cache/` so the same running app can serve them statically via web server rules.

## Design

### Config

**App-level** (per-website) property `cache` with values `none` (default) or `static`.

```yaml
# pushword.yaml
pushword:
  apps:
    - hosts: [localhost.dev]
      cache: static
```

### Page property

`cache` boolean stored in `customProperties` JSON column. Default: `true` (cached by default when site cache is enabled). Opt-out per page (e.g. forms, search).

### Cache directory

`public/cache/{host}/{slug}.html` (+ `.gz`, `.br` compressed variants).
Homepage: `public/cache/{host}/index.html`.

### Generation strategy

**On page save** (postPersist/postUpdate): invalidate that page's cached file (delete it).
**`pw:cache:clear`**: delete all cached files + warm all cacheable published pages (like Symfony's `cache:clear`).
**No on-request lazy generation** — keeps logic simple, avoids edge cases with logged-in users.

### Serving

Caddy/FrankenPHP `try_files` directive: if cached file exists AND no session cookie → serve static file. PHP never boots. Document the suggested Caddy config.

---

## Implementation — New bundle `packages/page-cache/`

### Files to create

```
packages/page-cache/
├── composer.json
├── src/
│   ├── PushwordPageCacheBundle.php
│   ├── DependencyInjection/
│   │   ├── Configuration.php          # cache config (default: none)
│   │   └── PageCacheExtension.php     # uses ExtensionTrait
│   ├── config/
│   │   └── services.php
│   ├── CacheClearCommand.php          # pw:cache:clear
│   ├── PageCacheGenerator.php         # renders page → file (reuses kernel request pattern from static-generator)
│   ├── PageCacheInvalidator.php       # Doctrine entity listener, deletes cached file on page save/delete
│   └── EventSubscriber/
│       └── AdminFormEventSubscriber.php  # adds PageCacheField to admin form
│   └── FormField/
│       └── PageCacheField.php         # BooleanField for page.cache custom property
```

### 1. Bundle scaffolding

- `PushwordPageCacheBundle` — same pattern as `PushwordStaticGeneratorBundle`
- `PageCacheExtension` — uses `ExtensionTrait`, config folder points to `__DIR__.'/../config'`
- `Configuration` — defines:
  - `app_fallback_properties: ['cache']`
  - `cache` node: scalar, default `'none'`
- `composer.json` — requires `pushword/core`, suggests `pushword/static-generator` (for Compressor)
- `services.php` — autowire all classes, bind `$publicDir` to `%pw.public_dir%`

### 2. PageCacheGenerator service

Responsible for rendering a single page to a static file. Reuses the kernel request pattern from `PageGenerator` in static-generator:

```php
class PageCacheGenerator
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly SiteRegistry $apps,
        private readonly string $publicDir,
    ) {}

    public function generatePage(Page $page): void
    {
        $siteConfig = $this->apps->get($page->host);
        if ('static' !== $siteConfig->getStr('cache', 'none')) return;
        if (!$page->isPublished()) return;
        if (true !== ($page->getCustomProperty('cache') ?? true)) return;
        if ($page->hasRedirection()) return;

        $url = '/' . $page->getRealSlug();
        $request = Request::create($url);
        $request->attributes->set('_pushword_page', $page);
        $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);

        if (Response::HTTP_OK !== $response->getStatusCode()) return;

        $content = $response->getContent();
        $path = $this->getCachePath($page);
        // Write HTML
        (new Filesystem())->dumpFile($path, $content);
        // Write compressed variants
        $this->writeCompressed($path, $content);
    }

    public function invalidatePage(Page $page): void
    {
        $path = $this->getCachePath($page);
        $fs = new Filesystem();
        foreach ([$path, $path.'.gz', $path.'.br'] as $file) {
            if ($fs->exists($file)) $fs->remove($file);
        }
    }

    public function clearHost(string $host): void
    {
        $dir = $this->publicDir.'/cache/'.$host;
        (new Filesystem())->remove($dir);
    }

    public function getCachePath(Page $page): string
    {
        $slug = '' === $page->getRealSlug() ? 'index' : $page->getRealSlug();
        return $this->publicDir.'/cache/'.$page->host.'/'.$slug.'.html';
    }

    private function writeCompressed(string $path, string $content): void
    {
        if (function_exists('gzencode')) {
            (new Filesystem())->dumpFile($path.'.gz', gzencode($content, 9));
        }
    }
}
```

**Key difference from static-generator's PageGenerator**: uses `HttpKernelInterface::SUB_REQUEST` instead of booting a separate kernel. This works for the on-save and CLI contexts. For CLI (`pw:cache:clear`), we boot the kernel normally and it's the main request.

**Note**: For the CLI warm command, we can't use SUB_REQUEST since there's no parent request. We'll use the same approach as static-generator: `$kernel->handle(Request::create($url))` with `MAIN_REQUEST`. Since the command handles one page at a time and terminates the kernel between pages (like the static generator does), this is clean.

### 3. CacheClearCommand (`pw:cache:clear`)

```
pw:cache:clear [host]          # clear + warm all (or specific host)
pw:cache:clear --no-warmup     # clear only
```

- Iterates all apps with `cache: static` (or just the specified host)
- For each: delete `public/cache/{host}/` directory
- Then warm: fetch all published pages with `PageRepository::getPublishedPages()`, filter by `cache` custom property, render each via kernel request (same pattern as static-generator's PageGenerator)
- Reuse `Compressor` from static-generator if available, otherwise just gzip
- Output progress (page count, timing)

### 4. PageCacheInvalidator (Doctrine entity listener)

```php
#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
```

- On postPersist/postUpdate: call `PageCacheGenerator::invalidatePage($page)` — deletes cached file so next warm regenerates it
- On preRemove: same — delete cached file
- Simple, no regeneration on save (avoids kernel-in-kernel complexity during admin request)

### 5. Admin form field

**PageCacheField** — extends `AbstractField`, returns `BooleanField` (EasyAdmin) for `customProperties[cache]`.

- Returns `null` (hidden) when site's `cache` config is `none`
- Default value: `true`

**AdminFormEventSubscriber** — listens to `pushword.admin.load_field`, adds `PageCacheField` to the "State" section (alongside publishedAt, metaRobots) when cache is enabled for the site.

### 6. .gitignore

Add `**/skeleton/public/cache` to root `.gitignore`.

### 7. Documentation

Add Caddy/FrankenPHP config suggestion:

```caddy
@cached {
    not header_regexp Cookie PHPSESSID
    file {
        try_files /cache/{host}{path}.html
    }
}
handle @cached {
    root * /path/to/public
    rewrite * /cache/{host}{path}.html
    file_server {
        precompressed br gzip
    }
}
```

### 8. Register bundle

Add to `packages/skeleton/config/bundles.php`.

---

## Files to modify

- `packages/skeleton/config/bundles.php` — register new bundle
- `packages/skeleton/config/packages/pushword.yaml` — add `cache: none` (or `static` for testing)
- `.gitignore` — add `public/cache`
- `packages/skeleton/composer.json` — add `pushword/page-cache` dependency

## Verification

1. `composer stan` — no errors
2. `composer rector` — code style OK
3. `composer console cache:clear` — no DI errors
4. Set `cache: static` in pushword.yaml for localhost.dev
5. `php bin/console pw:cache:clear` — generates files in `public/cache/localhost.dev/`
6. Verify `public/cache/localhost.dev/index.html` contains rendered homepage
7. Verify `.gz` sidecar exists
8. Edit a page in admin → verify its cached file is deleted
9. `pw:cache:clear` again → file regenerated
10. Test with `cache: none` → no files generated, no admin field visible
