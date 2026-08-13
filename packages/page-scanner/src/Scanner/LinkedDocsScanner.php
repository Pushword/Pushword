<?php

namespace Pushword\PageScanner\Scanner;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Override;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Helper;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Service\ErrorIgnoreRules;
use Pushword\PageScanner\Service\InlineDirective;

use function Safe\preg_match;
use function Safe\preg_match_all;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Permit to find error in image or link.
 *
 * @phpstan-import-type UrlCheckResult from ParallelUrlChecker
 */
final class LinkedDocsScanner extends AbstractScanner
{
    public const string PAGE_PROPERTY = 'pageScanLinksToIgnore';

    public const string INLINE_DIRECTIVE = 'page-scanner-ignore-link';

    /** @var array<string, array{exists: bool, page: ?Page, redirect: bool, derivativeError: ?string}> */
    private array $everChecked = [];

    /**
     * URIs this page exposes through a crawlable attribute, ie. anything but
     * `data-rot`. Obfuscated links are decrypted upstream and checked like any
     * other, so this is what tells the two apart afterwards.
     *
     * @var array<string, true>
     */
    private array $crawlableLinks = [];

    private int $linksCheckedCounter = 0;

    private ?DomCrawler $domPage = null;

    /** @var string[] */
    private array $toIgnore = [];

    /**
     * @var array<string, UrlCheckResult>
     */
    private array $urlExistCache = [];

    /**
     * Cache of all pages indexed by "host/slug" for fast lookup.
     *
     * @var array<string, Page>|null
     */
    private ?array $pageCache = null;

    private bool $collectMode = false;

    private bool $deferredExternalMode = false;

    private bool $checkUnpublished = false;

    /** @var string[] */
    private array $collectedExternalUrls = [];

    /** @var array<string, UrlCheckResult> */
    private array $externalUrlResults = [];

    /** @var array<int, array{url: string, pageId: int, pageHost: string, pageSlug: string, pageH1: string, pageMetaRobots: string, pageIgnorePatterns: string[]}> */
    private array $deferredExternalChecks = [];

    /**
     * @param string[] $linksToIgnore
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRegistry $siteRegistry,
        private readonly array $linksToIgnore,
        private readonly string $publicDir,
        private readonly string $mediaCacheDir,
        TranslatorInterface $translator,
        private readonly ?CacheInterface $externalUrlCache = null,
        private readonly int $externalUrlCacheTtl = 86400,
        private readonly int $externalUrlFailureCacheTtl = 3600,
        private readonly bool $skipExternalUrlCheck = false,
    ) {
        parent::__construct($translator);
    }

    public function enableCollectMode(): void
    {
        $this->collectMode = true;
        $this->collectedExternalUrls = [];
    }

    public function disableCollectMode(): void
    {
        $this->collectMode = false;
    }

    /**
     * Enable deferred external mode: returns internal errors immediately
     * while collecting external URLs for later parallel validation.
     */
    public function enableDeferredExternalMode(): void
    {
        $this->deferredExternalMode = true;
        $this->collectedExternalUrls = [];
        $this->deferredExternalChecks = [];
    }

    public function disableDeferredExternalMode(): void
    {
        $this->deferredExternalMode = false;
    }

    /**
     * Opt-in: report links pointing to pages that exist but are not published.
     * Off by default — the front-end already hides those links via the
     * HtmlUnpublishedLink filter, so reporting them is noise unless explicitly
     * requested via `pw:page-scan --check-unpublished`.
     */
    public function enableCheckUnpublished(): void
    {
        $this->checkUnpublished = true;
    }

    public function disableCheckUnpublished(): void
    {
        $this->checkUnpublished = false;
    }

    /**
     * Resolve deferred external URL errors after parallel validation.
     *
     * @return array<int, array<int, array{code: string, page: array{id: int, host: string, slug: string, h1: string, metaRobots: string}, message: string}>>
     */
    public function resolveDeferredExternalErrors(): array
    {
        $errors = [];
        foreach ($this->deferredExternalChecks as $check) {
            $url = $check['url'];
            $result = $this->externalUrlResults[$url] ?? true;
            if (true !== $result) {
                $message = '<code>'.$url.'</code> '.$result['message'];
                if (ErrorIgnoreRules::matches($check['pageIgnorePatterns'], $result['code'], $message)) {
                    continue;
                }

                $pageId = $check['pageId'];
                $errors[$pageId] ??= [];
                $errors[$pageId][] = [
                    'code' => $result['code'],
                    'page' => [
                        'id' => $check['pageId'],
                        'host' => $check['pageHost'],
                        'slug' => $check['pageSlug'],
                        'h1' => $check['pageH1'],
                        'metaRobots' => $check['pageMetaRobots'],
                    ],
                    'message' => $message,
                ];
            }
        }

        $this->deferredExternalChecks = [];

        return $errors;
    }

    /**
     * @return string[]
     */
    public function getCollectedExternalUrls(): array
    {
        return array_unique($this->collectedExternalUrls);
    }

    /**
     * @param array<string, UrlCheckResult> $results
     */
    public function setExternalUrlResults(array $results): void
    {
        $this->externalUrlResults = $results;
    }

    /**
     * Preload pages into cache for fast internal link checking.
     * Always loads all pages (all hosts) to support cross-host internal link resolution.
     */
    public function preloadPageCache(): void
    {
        if (null !== $this->pageCache) {
            return;
        }

        $this->pageCache = [];

        /** @var Page[] $pages */
        $pages = $this->entityManager->getRepository(Page::class)
            ->createQueryBuilder('p')
            ->getQuery()
            ->getResult();

        foreach ($pages as $page) {
            $this->pageCache[$page->host.'/'.$page->slug] = $page;
        }
    }

    /**
     * The URLs this page asks not to check at all — a link ignored here costs nothing
     * and reports nothing, where an ignored *finding* is still fetched every scan.
     *
     * @return array<string>
     */
    private function getPageScanLinksToIgnore(): array
    {
        $declared = $this->page->hasCustomProperty(self::PAGE_PROPERTY)
            ? $this->page->getCustomPropertyList(self::PAGE_PROPERTY) : [];

        return [...$declared, ...InlineDirective::patterns($this->page->mainContent, self::INLINE_DIRECTIVE)];
    }

    // Starting point called from AbstractSanner::scan
    protected function run(): void
    {
        $this->toIgnore = [...$this->linksToIgnore, ...$this->getPageScanLinksToIgnore()];

        $this->linksCheckedCounter = 0;
        $this->crawlableLinks = [];

        if ($this->page->hasRedirection()) {
            $this->checkLinkedDoc($this->page->getRedirectionUrl(), false);

            return;
        }

        // 2. Je récupère tout les liens et je les check
        // href="", data-rot="" data-img="", src="", data-bg
        if ('' === $this->pageHtml) {
            return;
        }

        if ('0' === $this->pageHtml) {
            return;
        }

        $this->checkLinkedDocs($this->getLinkedDocs());
    }

    /**
     * @param string|string[] $var
     */
    private function prepareForRegex(array|string $var): string
    {
        if (\is_string($var)) {
            return preg_quote($var, '/');
        }

        $var = array_map($this->prepareForRegex(...), $var);

        return '('.implode('|', $var).')';
    }

    private function isWebLink(string $url): bool
    {
        $toIgnore = [
            'https://wa.me/',
            'https://maps.app.goo.gl',
            'https://goo.gl/',
            'https://g.page/',
            'https://www.tripadvisor.fr/',
            'https://www.facebook.com/',
        ];
        foreach ($toIgnore as $ignore) {
            if (str_starts_with($url, $ignore)) {
                return false;
            }
        }

        return (bool) preg_match('@^((?:(http:|https:)//([\wà-üÀ-Ü-]+\.)+[\w-]+){0,1}(/?[\wà-üÀ-Ü~,;\-\./?%&+#=]*))$@', $url);
    }

    /**
     * @return string[]
     */
    private function getLinkedDocs(): array
    {
        $urlInAttributes = ' '.$this->prepareForRegex(['href', 'data-rot', 'src', 'data-img', 'data-bg']);
        $regex = '/'.$urlInAttributes.'=((["\'])([^\3]+)\3|([^\s>]+)[\s>])/iU';
        preg_match_all($regex, $this->stripCodeSamples(), $matches);

        if (null === $matches) {
            throw new Exception();
        }

        $linkedDocs = [];
        $matchesCount = is_countable($matches[0]) ? \count($matches[0]) : 0;
        for ($k = 0; $k < $matchesCount; ++$k) {
            // an unmatched group is an empty string, never unset: the quoted
            // value (4) is empty when the attribute value came unquoted (5).
            /** @var string */
            $uri = '' !== $matches[4][$k] ? $matches[4][$k] : $matches[5][$k]; // @phpstan-ignore-line
            $isDataRotAttribute = 'data-rot' === $matches[1][$k]; // @phpstan-ignore-line
            $uri = $isDataRotAttribute ? LinkProvider::decrypt($uri) : $uri;
            if ($this->isMailtoOrTelLink($uri) && ! $isDataRotAttribute) {
                $this->addError(ScanErrorCode::LinkMailto, '<code>'.$uri.'</code> '.$this->trans('page_scanObfuscateMail'));
            } elseif ('' !== $uri && $this->isWebLink($uri)) {
                if (! $isDataRotAttribute) {
                    $this->crawlableLinks[$uri] = true;
                }

                $linkedDocs[] = $uri;
            }
        }

        foreach ($this->extractSrcsetUris() as $uri) {
            $this->crawlableLinks[$uri] = true;
            $linkedDocs[] = $uri;
        }

        return array_unique($linkedDocs);
    }

    /**
     * `srcset` never matched the attribute regex above (the alternation's `src`
     * wants a literal `=` right after), so every URL a `<source srcset>` or
     * `<img srcset>` points at went unchecked — exactly where the responsive
     * derivatives live (`lg`/`xl` are what desktop browsers actually load).
     * A srcset value is a comma-separated list of "URL [descriptor]" entries.
     *
     * @return string[]
     */
    private function extractSrcsetUris(): array
    {
        preg_match_all('/\s(?:srcset|imagesrcset|data-srcset)=(["\'])(.*?)\1/i', $this->stripCodeSamples(), $matches);

        $srcsets = isset($matches[2]) && \is_array($matches[2]) ? $matches[2] : [];

        $uris = [];
        foreach ($srcsets as $srcset) {
            if (! \is_string($srcset)) {
                continue;
            }

            foreach (explode(',', $srcset) as $entry) {
                $uri = preg_split('/\s+/', trim($entry))[0] ?? '';
                if ('' !== $uri && $this->isWebLink($uri)) {
                    $uris[] = $uri;
                }
            }
        }

        return $uris;
    }

    /**
     * A URL inside a code sample illustrates markup, it does not link anywhere.
     * Markdown escapes `<` and `>` there but leaves the quotes intact, so an
     * `href="…"` written in a `<code>` block otherwise reads as a real attribute.
     */
    private function stripCodeSamples(): string
    {
        return preg_replace('#<(code|pre)\b[^>]*>.*?</\1>#is', '', $this->pageHtml) ?? $this->pageHtml;
    }

    private function isMailtoOrTelLink(string $uri): bool
    {
        return str_contains($uri, 'tel:') || str_contains($uri, 'mailto:');
    }

    private function removeParameters(string $url): string
    {
        if (str_contains($url, '?')) {
            $url = preg_replace('/(\?.*)$/', '', $url) ?? throw new Exception();
        }

        if (str_contains($url, '#')) {
            return preg_replace('/(#.*)$/', '', $url) ?? throw new Exception();
        }

        return $url;
    }

    private function removeBase(string $url): string
    {
        if ('' !== $this->page->host && str_starts_with($url, 'https://'.$this->page->host)) {
            return substr($url, \strlen('https://'.$this->page->host));
        }

        return $url;
    }

    private function isInternalHost(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && $this->siteRegistry->isKnownHost($host);
    }

    /**
     * An absolute URL on another host of the installation resolves against that host,
     * not the linking page's — but it reaches the very same things a root-relative link
     * does: a page, a media, a file under public/. Only the host the slug is looked up
     * against differs, so the resolution is shared with the root-relative branch.
     */
    private function checkInternalCrossHostLink(string $url, bool $checkRedirection): void
    {
        $parsed = parse_url($url);

        // The link may spell an alias host (a www., a port); pages are stored under the
        // site's main host, which is what the page cache and the repository key on.
        $host = $this->siteRegistry->findHost($parsed['host'] ?? '');

        $this->checkInternalUri($url, $parsed['path'] ?? '', $host, $checkRedirection);
    }

    /**
     * @param string $url the link as written, quoted back in the error message
     * @param string $uri its path, without scheme, host, query or fragment
     */
    private function checkInternalUri(string $url, string $uri, string $host, bool $checkRedirection): void
    {
        $target = $this->resolveUri($uri, $host);
        $page = $target['page'];

        if (! $target['exists']) {
            $this->addError(ScanErrorCode::LinkNotFound, '<code>'.$url.'</code> '.$this->trans('page_scanNotFound'));
        } elseif (null !== $target['derivativeError']) {
            $this->addError(ScanErrorCode::ImageDerivativeBroken, '<code>'.$url.'</code> '.$this->trans($target['derivativeError']));
        } elseif ($page instanceof Page && ! $page->isPublished()) {
            if ($this->checkUnpublished) {
                $this->addError(ScanErrorCode::LinkNotPublished, '<code>'.$url.'</code> '.$this->trans('page_scanNotPublished'));
            }
        } elseif ($checkRedirection && ($target['redirect'] || ($page instanceof Page && $page->hasRedirection()))) {
            $this->addError(ScanErrorCode::LinkRedirection, '<code>'.$url.'</code> '.$this->trans('page_scanIsRedirection'));
        } elseif ($this->isCrawlableLinkToNoindex($url, $page)) {
            $this->addError(ScanErrorCode::LinkNoindex, '<code>'.$url.'</code> '.$this->trans('page_scanNoindexLink'));
        }
    }

    /**
     * A crawlable link to a `noindex` page spends crawl budget and link equity on a
     * page that cannot rank. Obfuscating it with `link()` keeps it for visitors and
     * hides it from robots — so an already obfuscated link is exempt, even though it
     * reaches this check like any other (`data-rot` is decrypted upstream).
     *
     * Unpublished and redirection targets are caught by the arms above, so what is
     * left here is a page kept out of the index on purpose.
     */
    private function isCrawlableLinkToNoindex(string $url, ?Page $target): bool
    {
        return $target instanceof Page
            && isset($this->crawlableLinks[$url])
            && $target->hasNoindex();
    }

    public function getLinksCheckedCounter(): int
    {
        return $this->linksCheckedCounter;
    }

    /**
     * @param array<mixed> $linkedDocs
     */
    private function checkLinkedDocs(array $linkedDocs): void
    {
        foreach ($linkedDocs as $linkedDoc) {
            ++$this->linksCheckedCounter;
            if (! \is_string($linkedDoc)) {
                continue; // TODO Log ?!
            }

            $this->checkLinkedDoc($linkedDoc);
        }
    }

    private function mustIgnore(string $url): bool
    {
        return array_any($this->toIgnore, static fn (string $toIgnore): bool => fnmatch($toIgnore, $url));
    }

    private function checkLinkedDoc(string $url, bool $checkRedirection = true): void
    {
        // internal
        $uri = $this->removeBase($url);

        if ($this->mustIgnore($url) || ($uri !== $url && $this->mustIgnore($uri))) {
            return;
        }

        if (! isset($uri[0])) {
            $this->addError(ScanErrorCode::LinkEmpty, '<code>'.$url.'</code> empty link');

            return;
        }

        if ('/' === $uri[0]) {
            $this->checkInternalUri($url, $this->removeParameters($uri), $this->page->host, $checkRedirection);

            return;
        }

        if (str_starts_with($url, 'http')) {
            // Cross-host internal link: URL points to another host in the same Pushword installation
            if ($this->isInternalHost($url)) {
                $this->checkInternalCrossHostLink($url, $checkRedirection);

                return;
            }

            if ($this->skipExternalUrlCheck) {
                return;
            }

            if ($this->patchUnreachableDomain($url)) {
                return;
            }

            // In collect mode, just collect URLs for later parallel checking (no errors)
            if ($this->collectMode) {
                $this->collectedExternalUrls[] = $url;

                return;
            }

            // In deferred mode, collect URLs AND store page context for later error resolution
            if ($this->deferredExternalMode) {
                $this->collectedExternalUrls[] = $url;
                $this->deferredExternalChecks[] = [
                    'url' => $url,
                    'pageId' => (int) $this->page->id,
                    'pageHost' => $this->page->host,
                    'pageSlug' => $this->page->slug,
                    'pageH1' => $this->page->h1,
                    'pageMetaRobots' => $this->page->metaRobots,
                    // The page is long out of scope when the check resolves, so what
                    // it asked not to be told about travels with it.
                    'pageIgnorePatterns' => $this->pageIgnorePatterns,
                ];

                return;
            }

            // Use pre-computed results if available
            $result = $this->externalUrlResults[$url] ?? $this->urlExist($url);
            if (true !== $result) {
                $this->addError(ScanErrorCode::from($result['code']), '<code>'.$url.'</code> '.$result['message']);
            }

            return;
        }

        // anchor/bookmark/jump link
        if (str_starts_with($url, '#')) {
            if (! $this->targetExist(substr($url, 1))) {
                $this->addError(ScanErrorCode::LinkAnchor, '<code>'.$url.'</code> target not found');
            }

            return;
        }

        // Anything left is relative to the current page. Pushword serves every page
        // from the root, so a relative link resolves against a path owning no page,
        // and no internal tool sees it: LinkCollector, the link graph and the checks
        // above all key on a leading slash.
        $this->addError(ScanErrorCode::LinkRelative, '<code>'.$url.'</code> '.$this->trans('page_scanRelativeLink'));
    }

    private function patchUnreachableDomain(string $url): bool
    {
        return (bool) preg_match('/^https:\/\/(www)?\.?(example.tld|instagram.com)/i', $url);
    }

    #[Override]
    public function scan(Page $page, string $pageHtml): array
    {
        /** @return string[] */
        $this->domPage = new DomCrawler($pageHtml);

        return parent::scan($page, $pageHtml);
    }

    private function getDomPage(): DomCrawler
    {
        return $this->domPage ?? throw new Exception();
    }

    private function targetExist(string $target): bool
    {
        $node = $this->getDomPage()->filter('[name="'.$target.'"]')->getNode(0)
            ?? $this->getDomPage()->filter('[id="'.$target.'"]')->getNode(0);

        return null !== $node;
    }

    /**
     * @return UrlCheckResult
     */
    private function urlExist(string $url): true|array
    {
        // Check in-memory cache first
        if (isset($this->urlExistCache[$url])) {
            return $this->urlExistCache[$url];
        }

        // Use persistent cache if available
        if (null !== $this->externalUrlCache) {
            /** @var UrlCheckResult $result */
            $result = $this->externalUrlCache->get(ParallelUrlChecker::cacheKey($url), function (ItemInterface $item) use ($url): true|array {
                $checked = $this->checkUrlViaHttp($url);
                $item->expiresAfter(ParallelUrlChecker::ttlFor($checked, $this->externalUrlCacheTtl, $this->externalUrlFailureCacheTtl));

                return $checked;
            });

            return $this->urlExistCache[$url] = $result;
        }

        return $this->urlExistCache[$url] = $this->checkUrlViaHttp($url);
    }

    /**
     * @return UrlCheckResult
     */
    private function checkUrlViaHttp(string $url): true|array
    {
        $client = new ExtendedClient($url);
        $client
            ->setDefaultSpeedOptions()
            ->fakeBrowserHeader()
            ->setNoFollowRedirection()
            ->setMaximumResponseSize()
            ->setDownloadOnlyIf(Helper::checkStatusCode(...))
            ->setMobileUserAgent();
        $client->request();

        if (in_array($client->getCurlInfo(\CURLINFO_HTTP_CODE), [403, 410], true)) {
            return true;
        }

        if (200 !== $client->getCurlInfo(\CURLINFO_HTTP_CODE) && 0 !== $client->getCurlInfo(\CURLINFO_HTTP_CODE)) {
            /** @var string */
            $httpCode = $client->getCurlInfo(\CURLINFO_HTTP_CODE);

            return [
                'code' => ScanErrorCode::LinkStatus->value,
                'message' => $this->trans('page_scanStatusCode').' ('.$httpCode.')',
            ];
        }

        if ($client->getError() > 0) {
            return [
                'code' => ScanErrorCode::LinkUnreachable->value,
                'message' => $this->trans(
                    'page_scanUnreachable',
                    92832 === $client->getError() ? [' - errorMessage' => ''] : ['errorMessage' => $client->getErrorMessage()]
                ),
            ];
        }

        return true;
    }

    /**
     * Resolves the whole target, not just whether the slug exists: every check
     * downstream needs the page it landed on, so answering with a bare bool
     * silently exempted every page but the first to link a given target.
     *
     * @return array{exists: bool, page: ?Page, redirect: bool, derivativeError: ?string}
     */
    private function resolveUri(string $uri, string $host): array
    {
        $slug = ltrim($uri, '/');
        if ('' === $slug) {
            $slug = 'homepage';
        }

        $cacheKey = $host.'/'.$slug;

        if (isset($this->everChecked[$cacheKey])) {
            return $this->everChecked[$cacheKey];
        }

        $isMedia = str_starts_with($slug, 'media/');
        $page = null;
        $redirect = false;

        if (! $isMedia) {
            $page = $this->findPageInCacheOrDb($slug, $host);

            // No page owns this slug, but a destination page's redirectFrom (or a phantom
            // redirect) may still resolve it — surface it as a redirection, not a dead link.
            $redirect = ! $page instanceof Page
                && null !== $this->entityManager->getRepository(Page::class)->getRedirectFor($slug, $host);
        }

        $exists = $page instanceof Page
            || $redirect
            || ($isMedia && $this->mediaExistsBySlug(substr($slug, 6)))
            || file_exists($this->publicDir.'/'.$slug)
            || file_exists($this->publicDir.'/../'.$slug)
            || 'feed.xml' === $slug;

        $derivativeError = $exists && $isMedia ? $this->derivativeError(substr($slug, 6)) : null;

        return $this->everChecked[$cacheKey] = ['exists' => $exists, 'page' => $page, 'redirect' => $redirect, 'derivativeError' => $derivativeError];
    }

    /**
     * The media row in DB proves the source image exists — but what a
     * `media/<filter>/<name>` URL serves is a derivative FILE, generated
     * out-of-band by a background job with silent failure modes. A missing one
     * 404s wherever nothing regenerates it on demand (a static export), and a
     * zero-byte one is served as an empty 200 everywhere and never retried
     * (`pw:image:cache` reads it as already cached). The DB sees neither, so
     * this is the only check standing between "scan is green" and a broken
     * image in front of a visitor.
     *
     * @return ?string the message key describing how the derivative is broken
     */
    private function derivativeError(string $mediaSlug): ?string
    {
        if (1 !== substr_count($mediaSlug, '/')) {
            return null; // not a filter-prefixed URL: originals are not plain files on disk
        }

        // Derivatives are written to pw.media_cache_dir (its default lives under
        // public_dir, but a site may relocate it): <cache dir>/<filter>/<file>.
        $path = $this->mediaCacheDir.'/'.$mediaSlug;
        if (! file_exists($path)) {
            return 'page_scanDerivativeMissing';
        }

        if (0 === filesize($path)) {
            return 'page_scanDerivativeEmpty';
        }

        return null;
    }

    /**
     * Check if a media file exists by its slug (after stripping "media/" prefix).
     * Handles filter prefixes (e.g., "xs/image.webp") and format conversions (webp→jpg).
     */
    private function mediaExistsBySlug(string $mediaSlug): bool
    {
        $repo = $this->entityManager->getRepository(Media::class);
        $fileName = basename($mediaSlug);

        if (null !== $repo->findOneByFileName($fileName)) {
            return true;
        }

        // For filtered images, the extension may differ from the original (e.g., .webp from .jpg)
        $baseName = pathinfo($fileName, \PATHINFO_FILENAME);

        return array_any(['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], static fn (string $ext): bool => null !== $repo->findOneByFileName($baseName.'.'.$ext));
    }

    private function findPageInCacheOrDb(string $slug, string $host): ?Page
    {
        // Use cache if available
        if (null !== $this->pageCache) {
            return $this->pageCache[$host.'/'.$slug] ?? null;
        }

        // Fall back to database query
        return $this->entityManager->getRepository(Page::class)
            ->getPage($slug, $host);
    }
}
