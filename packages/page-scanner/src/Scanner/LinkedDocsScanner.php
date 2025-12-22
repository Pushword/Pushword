<?php

namespace Pushword\PageScanner\Scanner;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Override;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Helper;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;

use function Safe\preg_match;
use function Safe\preg_match_all;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Permit to find error in image or link.
 */
final class LinkedDocsScanner extends AbstractScanner
{
    /** @var array<string, bool> */
    private array $everChecked = [];

    private int $linksCheckedCounter = 0;

    private ?DomCrawler $domPage = null;

    /** @var string[] */
    private array $toIgnore = [];

    /**
     * @var array<string, string|true>
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

    /** @var string[] */
    private array $collectedExternalUrls = [];

    /** @var array<string, true|string> */
    private array $externalUrlResults = [];

    /** @var array<int, array{url: string, pageId: int, pageHost: string, pageSlug: string, pageH1: string, pageMetaRobots: string}> */
    private array $deferredExternalChecks = [];

    /**
     * @param string[] $linksToIgnore
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly array $linksToIgnore,
        private readonly string $publicDir,
        TranslatorInterface $translator,
        private readonly ?CacheInterface $externalUrlCache = null,
        private readonly int $externalUrlCacheTtl = 86400,
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
     * Resolve deferred external URL errors after parallel validation.
     *
     * @return array<int, array<int, array{page: array{id: int, host: string, slug: string, h1: string, metaRobots: string}, message: string}>>
     */
    public function resolveDeferredExternalErrors(): array
    {
        $errors = [];
        foreach ($this->deferredExternalChecks as $check) {
            $url = $check['url'];
            if (isset($this->externalUrlResults[$url]) && true !== $this->externalUrlResults[$url]) {
                $pageId = $check['pageId'];
                $errors[$pageId] ??= [];
                $errors[$pageId][] = [
                    'page' => [
                        'id' => $check['pageId'],
                        'host' => $check['pageHost'],
                        'slug' => $check['pageSlug'],
                        'h1' => $check['pageH1'],
                        'metaRobots' => $check['pageMetaRobots'],
                    ],
                    'message' => '<code>'.$url.'</code> '.$this->externalUrlResults[$url],
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
     * @param array<string, true|string> $results
     */
    public function setExternalUrlResults(array $results): void
    {
        $this->externalUrlResults = $results;
    }

    /**
     * Preload pages into cache for fast internal link checking.
     * If host is provided, only pages from that host are loaded.
     */
    public function preloadPageCache(string $host = ''): void
    {
        if (null !== $this->pageCache) {
            return;
        }

        $this->pageCache = [];

        $repo = $this->entityManager->getRepository(Page::class);
        $queryBuilder = $repo->createQueryBuilder('p');

        if ('' !== $host) {
            $queryBuilder->andWhere('p.host = :host')->setParameter('host', $host);
        }

        /** @var Page[] $pages */
        $pages = $queryBuilder->getQuery()->getResult();
        foreach ($pages as $page) {
            $key = $page->getHost().'/'.$page->getSlug();
            $this->pageCache[$key] = $page;
        }
    }

    /**
     * @return array<string>
     */
    private function getPageScanLinksToIgnore(): array
    {
        return $this->page->hasCustomProperty('pageScanLinksToIgnore')
            ? $this->page->getCustomPropertyList('pageScanLinksToIgnore') : [];
    }

    // Starting point called from AbstractSanner::scan
    protected function run(): void
    {
        $this->toIgnore = [...$this->linksToIgnore, ...$this->getPageScanLinksToIgnore()];

        $this->linksCheckedCounter = 0;

        if ($this->page->hasRedirection()) {
            $this->checkLinkedDoc($this->page->getRedirection(), false);

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
        preg_match_all($regex, $this->pageHtml, $matches);

        if (null === $matches) {
            throw new Exception();
        }

        $linkedDocs = [];
        $matchesCount = is_countable($matches[0]) ? \count($matches[0]) : 0;
        for ($k = 0; $k < $matchesCount; ++$k) {
            /** @var string */
            $uri = $matches[4][$k] ?? $matches[5][$k]; // @phpstan-ignore-line
            $isDataRotAttribute = 'data-rot' === $matches[1][$k]; // @phpstan-ignore-line
            $uri = $isDataRotAttribute ? LinkProvider::decrypt($uri) : $uri;
            // @phpstan-ignore-next-line
            $uri .= $matches[4][$k] ? '' : '#(obfuscate)'; // not elegant but permit to remember it's an obfuscate link
            if ($this->isMailtoOrTelLink($uri) && ! $isDataRotAttribute) {
                $this->addError('<code>'.$uri.'</code> '.$this->trans('page_scanObfuscateMail'));
            } elseif ('' !== $uri && $this->isWebLink($uri)) {
                $linkedDocs[] = $uri;
            }
        }

        return array_unique($linkedDocs);
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
        if ('' !== $this->page->getHost() && str_starts_with($url, 'https://'.$this->page->getHost())) {
            return substr($url, \strlen('https://'.$this->page->getHost()));
        }

        return $url;
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
        return array_any($this->toIgnore, fn ($toIgnore): bool => fnmatch($toIgnore, $url));
    }

    private function checkLinkedDoc(string $url, bool $checkRedirection = true): void
    {
        // internal
        $uri = $this->removeBase($url);

        if ($this->mustIgnore($url)) {
            return;
        }

        if (! isset($uri[0])) {
            $this->addError('<code>'.$url.'</code> empty link');

            return;
        }

        if ('/' === $uri[0]) {
            if (! $this->uriExist($this->removeParameters($uri))) {
                $this->addError('<code>'.$url.'</code> '.$this->trans('page_scanNotFound'));
            } elseif ($this->lastPageChecked instanceof Page && ! $this->lastPageChecked->isPublished()) {
                $this->addError('<code>'.$url.'</code> '.$this->trans('page_scanNotPublished'));
            } elseif ($checkRedirection && $this->lastPageChecked instanceof Page && $this->lastPageChecked->hasRedirection()) {
                $this->addError('<code>'.$url.'</code> '.$this->trans('page_scanIsRedirection'));
            }

            return;
        }

        // external
        if (str_starts_with($url, 'http')) {
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
                    'pageId' => (int) $this->page->getId(),
                    'pageHost' => $this->page->getHost(),
                    'pageSlug' => $this->page->getSlug(),
                    'pageH1' => $this->page->getH1(),
                    'pageMetaRobots' => $this->page->getMetaRobots(),
                ];

                return;
            }

            // Use pre-computed results if available
            if (isset($this->externalUrlResults[$url])) {
                $result = $this->externalUrlResults[$url];
                if (true !== $result) {
                    $this->addError('<code>'.$url.'</code> '.$result);
                }

                return;
            }

            // Fallback to synchronous check
            if (true !== ($errorMsg = $this->urlExist($url))) {
                $this->addError('<code>'.$url.'</code> '.$errorMsg);
            }

            return;
        }

        // anchor/bookmark/jump link
        if (str_starts_with($url, '#')) {
            if (! $this->targetExist(substr($url, 1))) {
                $this->addError('<code>'.$url.'</code> target not found');
            }

            return;
        }

        // TODO: log unchecked link dump($uri);
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

    private function urlExist(string $url): true|string
    {
        // Check in-memory cache first
        if (isset($this->urlExistCache[$url])) {
            return $this->urlExistCache[$url];
        }

        // Use persistent cache if available
        if (null !== $this->externalUrlCache) {
            $cacheKey = 'url_'.hash('xxh3', $url);

            /** @var true|string $result */
            $result = $this->externalUrlCache->get($cacheKey, function (ItemInterface $item) use ($url): true|string {
                $item->expiresAfter($this->externalUrlCacheTtl);

                return $this->checkUrlViaHttp($url);
            });

            return $this->urlExistCache[$url] = $result;
        }

        return $this->urlExistCache[$url] = $this->checkUrlViaHttp($url);
    }

    private function checkUrlViaHttp(string $url): true|string
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

            return $this->trans('page_scanStatusCode').' ('.$httpCode.')';
        }

        if ($client->getError() > 0) {
            return $this->trans(
                'page_scanUnreachable',
                92832 === $client->getError() ? [' - errorMessage' => ''] : ['errorMessage' => $client->getErrorMessage()]
            );
        }

        return true;
    }

    private ?Page $lastPageChecked = null;

    private function uriExist(string $uri): bool
    {
        $this->lastPageChecked = null;

        $slug = ltrim($uri, '/');

        if (isset($this->everChecked[$slug])) {
            return $this->everChecked[$slug];
        }

        $checkDatabase = ! str_starts_with($slug, 'media/'); // we avoid to check in db the media, file exists is enough

        if ($checkDatabase) {
            $this->lastPageChecked = $this->findPageInCacheOrDb($slug);
        }

        $this->everChecked[$slug] = (
            ! $this->lastPageChecked instanceof Page
                && ! file_exists($this->publicDir.'/'.$slug)
                && ! file_exists($this->publicDir.'/../'.$slug)
                && 'feed.xml' !== $slug
        ) ? false : true;

        return $this->everChecked[$slug];
    }

    private function findPageInCacheOrDb(string $slug): ?Page
    {
        // Use cache if available
        if (null !== $this->pageCache) {
            $hostKey = $this->page->getHost().'/'.$slug;

            return $this->pageCache[$hostKey] ?? null;
        }

        // Fall back to database query
        return $this->entityManager->getRepository(Page::class)
            ->getPage($slug, $this->page->getHost(), true);
    }
}
