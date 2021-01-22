<?php

namespace Pushword\PageScanner;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\UrlHarvester\Harvest;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\Router\RouterInterface as PwRouter;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment as Twig;

/**
 * Permit to find error in image or link.
 */
class PageScannerService
{
    use GenerateLivePathForTrait;
    use KernelTrait;

    protected AppConfig $app;

    protected EntityManagerInterface $em;
    protected $pageHtml;
    protected Twig $twig;
    protected $currentPage;
    protected $publicDir;
    protected $previousRequest;
    protected AppPool $apps;
    protected $linksCheckedCounter = 0;
    protected $errors = [];
    protected $everChecked = [];
    public static $appKernel;

    public function __construct(
        Twig $twig,
        EntityManagerInterface $em,
        string $publicDir,
        AppPool $apps,
        PwRouter $router,
        KernelInterface $kernel
    ) {
        $this->twig = $twig;
        $this->router = $router;
        $this->router->setUseCustomHostPath(false);
        $this->em = $em;
        $this->publicDir = $publicDir;
        $this->apps = $apps;

        static::loadKernel($kernel);
        static::$appKernel->getContainer()->get('pushword.router')->setUseCustomHostPath(false);
    }

    protected function resetErrors()
    {
        $this->errors = [];
    }

    public function scan(PageInterface $page)
    {
        $this->app = $this->apps->get($page->getHost());
        $this->currentPage = $page;
        $this->resetErrors();
        $this->pageHtml = '';

        if (false !== $page->getRedirection()) {
            // check $page->getRedirection() return 20X

            return true; // or status code
        }

        $liveUri = $this->generateLivePathFor($page);
        $this->pageHtml = $this->getHtml($liveUri);

        // 2. Je récupère tout les liens et je les check
        // href="", data-rot="" data-img="", src="", data-bg
        if ($this->pageHtml) {
            $this->checkLinkedDocs($this->getLinkedDocs());
        }

        return empty($this->errors) ? true : $this->errors;
    }

    protected function getHtml($liveUri)
    {
        $request = Request::create($liveUri);
        $response = static::$appKernel->handle($request);

        if ($response->isRedirect()) {
            //. $linkedDocs[] = $response->headers->get('location');
            // todo check redirection
            return;
        } elseif (200 != $response->getStatusCode()) {
            file_put_contents('debug', $response);
            $this->addError('error on generating the page ('.$response->getStatusCode().')');

            return;
        }

        return $response->getContent();
    }

    protected function addError($message)
    {
        $this->errors[] = [
            'message' => $message,
            'page' => [
                'id' => $this->currentPage->getId(),
                'slug' => $this->currentPage->getSlug(),
                'h1' => $this->currentPage->getH1(),
                'metaRobots' => $this->currentPage->getMetaRobots(),
                'host' => $this->currentPage->getHost(),
            ],
        ];
    }

    protected static function prepareForRegex($var)
    {
        if (\is_string($var)) {
            return preg_quote($var, '/');
        }

        $var = array_map('static::prepareForRegex', $var);

        return '('.implode('|', $var).')';
    }

    protected static function isWebLink(string $url)
    {
        return preg_match('@^((?:(http:|https:)//([\w\d-]+\.)+[\w\d-]+){0,1}(/?[\w~,;\-\./?%&+#=]*))$@', $url);
    }

    protected function getLinkedDocs(): array
    {
        $urlInAttributes = ' '.self::prepareForRegex(['href', 'data-rot', 'src', 'data-img', 'data-bg']);
        $regex = '/'.$urlInAttributes.'=((["\'])([^\3]+)\3|([^\s>]+)[\s>])/iU';
        preg_match_all($regex, $this->pageHtml, $matches);

        $linkedDocs = [];
        $matchesCount = \count($matches[0]);
        for ($k = 0; $k < $matchesCount; ++$k) {
            $uri = isset($matches[4][$k]) ? $matches[4][$k] : $matches[5][$k];
            $uri = 'data-rot' == $matches[1][$k] ? str_rot13($uri) : $uri;
            $uri = strtok($uri, '#');
            $uri = $this->removeBase($uri);
            if ('' !== $uri && self::isWebLink($uri)) {
                $linkedDocs[] = $uri;
            }
        }

        return array_unique($linkedDocs);
    }

    protected function removeBase($url)
    {
        if (0 === strpos($url, 'https://'.$this->app->getMainHost())) {
            return substr($url, \strlen('https://'.$this->app->getMainHost()));
        }

        return $url;
    }

    public function getLinksCheckedCounter()
    {
        return $this->linksCheckedCounter;
    }

    protected function checkLinkedDocs(array $linkedDocs)
    {
        foreach ($linkedDocs as $uri) {
            ++$this->linksCheckedCounter;
            if (! \is_string($uri)) {
                continue;
            }
            if (('/' == $uri[0] && ! $this->uriExist($uri))
                || (0 === strpos($uri, 'http') && ! $this->urlExist($uri))) {
                $this->addError('<code>'.$uri.'</code> introuvable');
            }
        }
    }

    /**
     * this is really slow on big website.
     */
    protected function urlExist(string $uri): bool
    {
        $harvest = Harvest::fromUrl(
            $uri,
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.107 Safari/537.36',
            'en,en-US;q=0.5',
            $this->previousRequest
        );

        if (\is_int($harvest) || 200 !== $harvest->getResponse()->getStatusCode()) {
            return false;
        }

        $this->previousRequest = $harvest->getResponse()->getRequest();

        return true;
    }

    protected function uriExist(string $uri): bool
    {
        $slug = ltrim($uri, '/');

        if (isset($this->everChecked[$slug])) {
            return $this->everChecked[$slug];
        }

        $checkDatabase = 0 !== strpos($slug, 'media/'); // we avoid to check in db the media, file exists is enough
        $page = true !== $checkDatabase ? null : $this->em->getRepository(\get_class($this->currentPage))
            ->findOneBy(['slug' => '' == $slug ? 'homepage' : $slug]); // todo add domain check (currentPage domain)

        $this->everChecked[$slug] = (
            null === $page
                && ! file_exists($this->publicDir.'/'.$slug)
                && 'feed.xml' !== $slug
        ) ? false : true;

        return $this->everChecked[$slug];
    }
}
