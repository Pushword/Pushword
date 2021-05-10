<?php

namespace Pushword\PageScanner\Scanner;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\UrlHarvester\Harvest;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Twig\AppExtension;

/**
 * Permit to find error in image or link.
 */
final class LinkedDocsScanner extends AbstractScanner
{
    private $everChecked = [];

    private $linksCheckedCounter = 0;

    private $previousRequest;

    private EntityManagerInterface $entityManager;

    private string $publicDir;

    public function __construct(EntityManagerInterface $entityManager, string $publicDir)
    {
        $this->publicDir = $publicDir;
        $this->entityManager = $entityManager;
    }

    public function run(): void
    {
        $this->linksCheckedCounter = 0;

        if (false !== $this->page->getRedirection()) {
            $this->checkLinkedDoc($this->removeBase($this->page->getRedirection()));

            return;
        }

        // 2. Je récupère tout les liens et je les check
        // href="", data-rot="" data-img="", src="", data-bg
        if ($this->pageHtml) {
            $this->checkLinkedDocs($this->getLinkedDocs());
        }

        return;
    }

    private static function prepareForRegex($var)
    {
        if (\is_string($var)) {
            return preg_quote($var, '/');
        }

        $var = array_map('static::prepareForRegex', $var);

        return '('.implode('|', $var).')';
    }

    private static function isWebLink(string $url)
    {
        return preg_match('@^((?:(http:|https:)//([\w\d-]+\.)+[\w\d-]+){0,1}(/?[\w~,;\-\./?%&+#=]*))$@', $url);
    }

    private function getLinkedDocs(): array
    {
        $urlInAttributes = ' '.self::prepareForRegex(['href', 'data-rot', 'src', 'data-img', 'data-bg']);
        $regex = '/'.$urlInAttributes.'=((["\'])([^\3]+)\3|([^\s>]+)[\s>])/iU';
        preg_match_all($regex, $this->pageHtml, $matches);

        $linkedDocs = [];
        $matchesCount = \count($matches[0]);
        for ($k = 0; $k < $matchesCount; ++$k) {
            $uri = $matches[4][$k] ?: $matches[5][$k];
            $uri = 'data-rot' == $matches[1][$k] ? AppExtension::decrypt($uri) : $uri;
            if (strpos($uri, '#') === 0) {
                // anchor link
            } else {
                $uri = strtok($uri, '#'); // remove everything after #
            }
            $uri = $uri.($matches[4][$k] ? '' : '#(encrypt)'); // not elegant but permit to remember it's an encrypted link
            $uri = $this->removeBase($uri);
            if (self::isMailtoOrTelLink($uri) && 'data-rot' != $matches[1][$k]) {
                $this->addError('<code>'.$uri.'</code> '.$this->trans('page_scan.encrypt_mail'));
            } elseif ('' !== $uri && self::isWebLink($uri)) {
                $linkedDocs[] = $uri;
            }
        }

        return array_unique($linkedDocs);
    }

    private static function isMailtoOrTelLink(string $uri): bool
    {
        if (false !== strpos($uri, 'tel:') || false !== strpos($uri, 'mailto:')) {
            return true;
        }

        return false;
    }

    private function removeBase($url)
    {
        if ($this->page->getHost() && 0 === strpos($url, 'https://'.$this->page->getHost())) {
            return substr($url, \strlen('https://'.$this->page->getHost()));
        }

        return $url;
    }

    public function getLinksCheckedCounter(): int
    {
        return $this->linksCheckedCounter;
    }

    private function checkLinkedDocs(array $linkedDocs): void
    {
        foreach ($linkedDocs as $uri) {
            ++$this->linksCheckedCounter;
            if (! \is_string($uri)) {
                continue; // TODO Log ?!
            }
            $this->checkLinkedDoc($uri);
        }
    }

    private function checkLinkedDoc(string $uri): void
    {
        // internal
        if ('/' == $uri[0]) {
            if (! $this->uriExist($uri)) {
                $this->addError('<code>'.$uri.'</code> '.$this->trans('page_scan.not_found'));
            }
            return;
        }

        // external
        if (0 === strpos($uri, 'http'))
            {if (true !== ($errorMsg = $this->urlExist($uri))) {
            $this->addError('<code>'.$uri.'</code> '.$errorMsg);
            }
            return;
        }

        // anchor/bookmark/jump link
        if (strpos($uri, '#')===0) {
            if (! $this->targetExist(substr($uri, 1))) {
                $this->addError('<code>'.$uri.'</code> target not found');
            }

            return;
        }

        // TODO: log unchecked link dump($uri);
    }

    private function targetExist($target): bool
    {
        // todo: prefer a dom explorer
        $regex = '/(id|name)=(["\'])([^\2]* |)*'.preg_quote($target, '/').'( [^\2]*\2|\2)/i';
        if (preg_match($regex, $this->pageHtml) !== false)
            return true;

        return false;
    }

    /**
     * this is really slow on big website.
     *
     * @return true|string
     */
    private function urlExist(string $uri)
    {
        $harvest = Harvest::fromUrl(
            $uri,
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.107 Safari/537.36',
            'en,en-US;q=0.5',
            $this->previousRequest
        );

        if (\is_int($harvest)) {
            return $this->trans('page_scan.unreachable');
        } elseif (200 !== $errorCode = $harvest->getResponse()->getStatusCode()) {
            return $this->trans('page_scan.status_code').' ('.$errorCode.')';
        } elseif (! $harvest->isCanonicalCorrect()) {
            return $this->trans('page_scan.canonical').' ('.$harvest->getCanonical().')';
        }

        $this->previousRequest = $harvest->getResponse()->getRequest();

        return true;
    }

    private function uriExist(string $uri): bool
    {
        $slug = ltrim($uri, '/');

        if (isset($this->everChecked[$slug])) {
            return $this->everChecked[$slug];
        }

        $checkDatabase = 0 !== strpos($slug, 'media/'); // we avoid to check in db the media, file exists is enough

        $page = true !== $checkDatabase ? null :
            Repository::getPageRepository($this->entityManager, \get_class($this->page))
                ->getPage($slug, $this->page->getHost(), true);

        $this->everChecked[$slug] = (
            null === $page
                && ! file_exists($this->publicDir.'/'.$slug)
                && ! file_exists($this->publicDir.'/../'.$slug)
                && 'feed.xml' !== $slug
        ) ? false : true;

        return $this->everChecked[$slug];
    }
}
