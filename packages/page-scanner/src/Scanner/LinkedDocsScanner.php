<?php

namespace Pushword\PageScanner\Scanner;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Helper;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\Url;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;

use function Safe\preg_match;
use function Safe\preg_match_all;

use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Permit to find error in image or link.
 *
 * @psalm-suppress PropertyNotSetInConstructor
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
     * @param string[] $linksToIgnore
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly array $linksToIgnore,
        private readonly string $publicDir,
        TranslatorInterface $translator,
    ) {
        parent::__construct($translator);
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
        return (bool) preg_match('@^((?:(http:|https:)//([\wà-üÀ-Ü-]+\.)+[\w-]+){0,1}(/?[\wà-üÀ-Ü~,;\-\./?%&+#=]*))$@', $url);
    }

    /**
     * @return string[]
     *
     * @psalm-suppress all
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
            $uri = $matches[4][$k] ?? $matches[5][$k];
            $uri = 'data-rot' == $matches[1][$k] ? LinkProvider::decrypt($uri) : $uri;
            $uri .= $matches[4][$k] ? '' : '#(encrypt)'; // not elegant but permit to remember it's an encrypted link
            if ($this->isMailtoOrTelLink($uri) && 'data-rot' != $matches[1][$k]) {
                $this->addError('<code>'.$uri.'</code> '.$this->trans('page_scan.encrypt_mail'));
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
        foreach ($this->toIgnore as $toIgnore) {
            if (fnmatch($toIgnore, $url)) {
                return true;
            }
        }

        return false;
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
                $this->addError('<code>'.$url.'</code> '.$this->trans('page_scan.not_found'));
            } elseif ($checkRedirection && $this->lastPageChecked instanceof Page && $this->lastPageChecked->hasRedirection()) {
                $this->addError('<code>'.$url.'</code> '.$this->trans('page_scan.is_redirection'));
            }

            return;
        }

        // external
        if (str_starts_with($url, 'http')) {
            if (! $this->patchUnreachableDomain($url) && true !== ($errorMsg = $this->urlExist($url))) {
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

    private function getDomPage(): DomCrawler
    {
        if (null !== $this->domPage) {
            return $this->domPage;
        }

        return new DomCrawler($this->pageHtml);
    }

    private function targetExist(string $target): bool
    {
        return null !== $this->getDomPage()
            ->filter('[name*="'.$target.'"],[id*="'.$target.'"]')
            ->getNode(0);
    }

    /**
     * this is really slow on big website.
     *
     * @return true|string
     */
    private function urlExist(string $url)
    {
        if (isset($this->urlExistCache[$url])) {
            return $this->urlExistCache[$url];
        }

        $client = new ExtendedClient($url);
        $client
            ->setDefaultSpeedOptions()
            ->fakeBrowserHeader()
            ->setNoFollowRedirection()
            ->setMaximumResponseSize()
            ->setDownloadOnlyIf(static fn (string $line, int $expected = 200): bool => Helper::checkStatusCode($line, $expected))
            ->setMobileUserAgent();
        // if ($this->proxy) { $client->setProxy($this->proxy); }
        $client->request();

        if (200 !== $client->getCurlInfo(\CURLINFO_HTTP_CODE) && 0 !== $client->getCurlInfo(\CURLINFO_HTTP_CODE)) {
            /** @var string */
            $httpCode = $client->getCurlInfo(\CURLINFO_HTTP_CODE);

            return $this->urlExistCache[$url] = $this->trans('page_scan.status_code').' ('.$httpCode.')';
        }

        if ($client->getError() > 0) {
            return $this->urlExistCache[$url] = $this->trans(
                'page_scan.unreachable',
                92832 === $client->getError() ? [' - errorMessage' => ''] : ['errorMessage' => $client->getErrorMessage()]
            );
        }

        $canonical = new CanonicalExtractor(new Url($url), new DomCrawler($client->getResponse()->getBody()));
        if (! $canonical->ifCanonicalExistsIsItCorrectOrPartiallyCorrect()) {
            return $this->urlExistCache[$url] = $this->trans('page_scan.canonical').' ('.($canonical->get() ?? 'null').')';
        }

        return $this->urlExistCache[$url] = true;
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

        $this->lastPageChecked = $checkDatabase ? $this->entityManager->getRepository(Page::class)
            ->getPage($slug, $this->page->getHost(), true) :
            null;

        $this->everChecked[$slug] = (
            ! $this->lastPageChecked instanceof Page
                && ! file_exists($this->publicDir.'/'.$slug)
                && ! file_exists($this->publicDir.'/../'.$slug)
                && 'feed.xml' !== $slug
        ) ? false : true;

        return $this->everChecked[$slug];
    }
}
