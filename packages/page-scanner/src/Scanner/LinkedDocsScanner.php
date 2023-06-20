<?php

namespace Pushword\PageScanner\Scanner;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\Url;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Twig\AppExtension;
use Pushword\Core\Utils\F;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

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
     * @var array<string, mixed>
     */
    private array $urlExistCache = [];

    /**
     * @param string[] $linksToIgnore
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly array $linksToIgnore,
        private readonly string $publicDir
    ) {
    }

    /**
     * @return string[]
     */
    private function getPageScanLinksToIgnore(): array
    {
        $pageScanLinksToIgnore = $this->page->getCustomProperty('pageScanLinksToIgnore') ?? [];

        return \is_array($pageScanLinksToIgnore) ? $pageScanLinksToIgnore : [];
    }

    // Starting point called from AbstractSanner::scan
    protected function run(): void
    {
        $this->toIgnore = array_merge(
            $this->linksToIgnore,
            $this->getPageScanLinksToIgnore()
        );

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

        $var = array_map('static::prepareForRegex', $var); // @phpstan-ignore-line

        return '('.implode('|', $var).')';
    }

    private function isWebLink(string $url): bool
    {
        return (bool) \Safe\preg_match('@^((?:(http:|https:)//([\wà-üÀ-Ü-]+\.)+[\w-]+){0,1}(/?[\wà-üÀ-Ü~,;\-\./?%&+#=]*))$@', $url);
    }

    /**
     * @return string[]
     */
    private function getLinkedDocs(): array
    {
        $urlInAttributes = ' '.$this->prepareForRegex(['href', 'data-rot', 'src', 'data-img', 'data-bg']);
        $regex = '/'.$urlInAttributes.'=((["\'])([^\3]+)\3|([^\s>]+)[\s>])/iU';
        \Safe\preg_match_all($regex, $this->pageHtml, $matches);

        $linkedDocs = [];
        $matchesCount = is_countable($matches[0]) ? \count($matches[0]) : 0;
        for ($k = 0; $k < $matchesCount; ++$k) {
            $uri = $matches[4][$k] ?? $matches[5][$k];
            $uri = 'data-rot' == $matches[1][$k] ? AppExtension::decrypt($uri) : $uri;
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
            $url = F::preg_replace_str('/(\?.*)$/', '', $url);
        }

        if (str_contains($url, '#')) {
            return F::preg_replace_str('/(#.*)$/', '', $url);
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

        if ('/' == $uri[0]) {
            if (! $this->uriExist($this->removeParameters($uri))) {
                $this->addError('<code>'.$url.'</code> '.$this->trans('page_scan.not_found'));
            } elseif ($checkRedirection && $this->lastPageChecked instanceof PageInterface && $this->lastPageChecked->hasRedirection()) {
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
        return (bool) \Safe\preg_match('/^https:\/\/(www)?\.?(example.tld|instagram.com)/i', $url);
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
     * @return bool|mixed|string
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
            ->setDownloadOnlyIf(static fn (string $line, int $expected = 200): bool => \PiedWeb\Curl\Helper::checkStatusCode($line, $expected))
            ->setMobileUserAgent();
        // if ($this->proxy) { $client->setProxy($this->proxy); }
        $client->request();

        if (200 !== $client->getCurlInfo(\CURLINFO_HTTP_CODE) && 0 !== $client->getCurlInfo(\CURLINFO_HTTP_CODE)) {
            return $this->urlExistCache[$url] = $this->trans('page_scan.status_code').' ('.$client->getCurlInfo(\CURLINFO_HTTP_CODE).')';
        }

        if ($client->getError() > 0) {
            return $this->urlExistCache[$url] = $this->trans(
                'page_scan.unreachable',
                92832 === $client->getError() ? [' - errorMessage' => ''] : ['errorMessage' => $client->getErrorMessage()]
            );
        }

        $canonical = new CanonicalExtractor(new Url($url), new DomCrawler($client->getResponse()->getBody()));
        if (! $canonical->ifCanonicalExistsIsItCorrectOrPartiallyCorrect()) {
            return $this->urlExistCache[$url] = $this->trans('page_scan.canonical').' ('.$canonical->get().')';
        }

        return $this->urlExistCache[$url] = true;
    }

    private ?PageInterface $lastPageChecked = null;

    private function uriExist(string $uri): bool
    {
        $this->lastPageChecked = null;

        $slug = ltrim($uri, '/');

        if (isset($this->everChecked[$slug])) {
            return $this->everChecked[$slug];
        }

        $checkDatabase = ! str_starts_with($slug, 'media/'); // we avoid to check in db the media, file exists is enough

        $this->lastPageChecked = $checkDatabase ? Repository::getPageRepository($this->entityManager, $this->page::class)
            ->getPage($slug, $this->page->getHost(), true) :
            null;

        $this->everChecked[$slug] = (
            ! $this->lastPageChecked instanceof PageInterface
                && ! file_exists($this->publicDir.'/'.$slug)
                && ! file_exists($this->publicDir.'/../'.$slug)
                && 'feed.xml' !== $slug
        ) ? false : true;

        return $this->everChecked[$slug];
    }
}
