<?php

namespace Pushword\StaticGenerator;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\Router\RouterInterface;
use Pushword\Core\Entity\PageInterface as Page;
use Pushword\Core\Repository\PageRepositoryInterface;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;
use WyriHaximus\HtmlCompress\Factory as HtmlCompressor;
use WyriHaximus\HtmlCompress\HtmlCompressorInterface;

/**
 * Generate 1 App.
 */
class StaticAppGenerator
{
    use GenerateLivePathForTrait;
    use KernelTrait;

    /**
     * @var array
     */
    protected $dontCopy = ['index.php', '.htaccess', 'robots.txt'];

    /**
     * @var PageRepositoryInterface
     */
    protected $pageRepository;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var Twig
     */
    protected $twig;

    /**
     * @var string
     */
    protected $webDir;

    /**
     * @var AppPool
     */
    protected $apps;
    protected $app;
    protected $staticDomain;
    protected $mustGetPagesWithoutHost = true;

    /** var @string */
    protected $staticDir;

    /**
     * @var RequestStack
     */
    protected $requesStack;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var HtmlCompressorInterface
     */
    protected $parser;

    /**
     * @var ParameterBagInterface
     */
    protected $params;

    /** @var RouterInterface */
    protected $router;

    /**
     * Used in .htaccess generation.
     *
     * @var string
     */
    protected $redirections = '';

    public function __construct(
        PageRepositoryInterface $pageRepository,
        Twig $twig,
        ParameterBagInterface $params,
        RequestStack $requesStack,
        TranslatorInterface $translator,
        RouterInterface $router,
        string $webDir,
        KernelInterface $kernel,
        AppPool $apps
    ) {
        $this->pageRepository = $pageRepository;
        $this->filesystem = new Filesystem();
        $this->twig = $twig;
        $this->params = $params;
        $this->requesStack = $requesStack;
        $this->webDir = $webDir;
        $this->translator = $translator;
        $this->router = $router;
        $this->router->setUseCustomHostPath(false);
        $this->apps = $apps;
        $this->parser = HtmlCompressor::construct();

        if (! method_exists($this->filesystem, 'dumpFile')) {
            throw new \RuntimeException('Method dumpFile() is not available. Upgrade your Filesystem.');
        }

        static::loadKernel($kernel);
        $this->kernel = $kernel;
    }

    /**
     * Undocumented function
     *
     * @param string $filter
     * @return integer the number of site generated
     */
    public function generateAll(?string $filter = null): int
    {
        $i = 0;
        foreach ($this->apps->getHosts() as $host) {
            if ($filter && $filter != $host) {
                continue;
            }

            $this->generate($host);

            ++$i;
        }

        return $i;
    }

    public function generateFromHost($host)
    {
        return $this->generateAll($host);
    }

    /**
     * Main Logic is here.
     *
     * @throws \RuntimeException
     * @throws \LogicException
     */
    protected function generate($host, $mustGetPagesWithoutHost = false)
    {
        $this->app = $this->apps->switchCurrentApp($host)->get();
        $this->mustGetPagesWithoutHost = $this->app->isFirstApp();

        $this->filesystem->remove($this->getStaticDir());
        $this->filesystem->mkdir($this->getStaticDir());
        $this->generatePages();
        $this->generateSitemaps();
        $this->generateErrorPages();
        $this->generateServerManagerFile();
        $this->copyAssets();
        $this->copyMediaToDownload();
    }

    /**
     * Symlink doesn't work on github page, symlink only for apache if conf say OK to symlink.
     */
    protected function mustSymlink()
    {
        return 'github' == $this->app->get('static_generate_for') ? false : $this->app->get('static_symlink');
    }

    /**
     * Generate .htaccess for Apache or CNAME for github
     * Must be run after generatePages() !!
     */
    protected function generateServerManagerFile()
    {
        if ('apache' == $this->app->get('static_generate_for')) {
            $this->generateHtaccess();
        } elseif ('github' == $this->app->get('static_generate_for')) {
            $this->generateCname();
        }
    }

    // todo
    // docs
    // https://help.github.com/en/github/working-with-github-pages/managing-a-custom-domain-for-your-github-pages-site
    protected function generateCname()
    {
        $this->filesystem->dumpFile($this->getStaticDir().'/CNAME', $this->app->getMainHost());
    }

    protected function getStaticDir()
    {
        $staticDir = $this->app->get('static_dir');
        $staticDir = $staticDir ?: $this->webDir.'/../'.$this->app->getMainHost();

        $realPath = realpath($staticDir);

        return false !== $realPath ? $realPath : $staticDir;
    }

    protected function generateHtaccess()
    {
        $htaccess = $this->twig->render('@pwStaticGenerator/htaccess.twig', [
            'domain' => $this->app->getMainHost(),
            'redirections' => $this->redirections,
        ]);
        $this->filesystem->dumpFile($this->getStaticDir().'/.htaccess', $htaccess);
    }

    protected function copy(string $file): void
    {
        if (file_exists($file)) {
            copy(
                str_replace($this->params->get('kernel.project_dir').'/', '../', $this->webDir.'/'.$file),
                $this->getStaticDir().'/'.$file
            );
        }
    }

    /**
     * Copy (or symlink) for all assets in public
     * (and media previously generated by liip in public).
     */
    protected function copyAssets(): void
    {
        $symlink = $this->mustSymlink();

        $dir = dir($this->webDir);
        while (false !== $entry = $dir->read()) {
            if ('.' == $entry || '..' == $entry) {
                continue;
            }
            if (\in_array($entry, $this->dontCopy) || \in_array($entry, $this->app->get('static_dont_copy'))) {
                continue;
            }
            //$this->symlink(
            if (true === $symlink) {
                $this->filesystem->symlink(
                    str_replace($this->params->get('kernel.project_dir').'/', '../', $this->webDir.'/'.$entry),
                    $this->getStaticDir().'/'.$entry
                );
            } else {
                $action = is_file($this->webDir.'/'.$entry) ? 'copy' : 'mirror';
                $this->filesystem->$action($this->webDir.'/'.$entry, $this->getStaticDir().'/'.$entry);
            }
        }
        $dir->close();
    }

    /**
     * Copy or Symlink "not image" media to download folder.
     *
     * @return void
     */
    protected function copyMediaToDownload()
    {
        $symlink = $this->mustSymlink();

        if (! file_exists($this->getStaticDir().'/download')) {
            $this->filesystem->mkdir($this->getStaticDir().'/download/');
            $this->filesystem->mkdir($this->getStaticDir().'/download/media');
        }

        $dir = dir($this->webDir.'/../media');
        while (false !== $entry = $dir->read()) {
            if ('.' == $entry || '..' == $entry) {
                continue;
            }
            // if the file is an image, it's ever exist (maybe it's slow to check every files)
            if (! file_exists($this->webDir.'/media/default/'.$entry)) {
                if (true === $symlink) {
                    $this->filesystem->symlink(
                        '../../../media/'.$entry,
                        $this->getStaticDir().'/download/media/'.$entry
                    );
                } else {
                    $this->filesystem->copy(
                        $this->webDir.'/../media/'.$entry,
                        $this->getStaticDir().'/download/media/'.$entry
                    );
                }
            }
        }

        //$this->filesystem->$action($this->webDir.'/../media', $this->getStaticDir().'/download/media');
    }

    protected function generateSitemaps(): void
    {
        foreach ($this->app->getLocales() as $locale) { // todo, find locale by my self via repo
            foreach (['txt', 'xml'] as $format) {
                $this->generateSitemap($locale, $format);
            }

            $this->generateFeed($locale);
            $this->generateRobotsTxt($locale);
        }
    }

    protected function generateSitemap($locale, $format)
    {
        $liveUri = $this->generateLivePathFor(
            $this->app->getMainHost(),
            'pushword_page_sitemap',
            ['locale' => $locale, '_format' => $format]
        );

        $staticFile = $this->getStaticDir().'/'.$locale.'/sitemap.'.$format; // todo get it from URI removing host
        $this->saveAsStatic($liveUri, $staticFile);

        if ($this->params->get('locale') == $locale) {
            $staticFile = $this->getStaticDir().'/sitemap.'.$format;
            $this->saveAsStatic($liveUri, $staticFile);
        }
    }

    protected function generateFeed($locale)
    {
        $liveUri = $this->generateLivePathFor(
            $this->app->getMainHost(),
            'pushword_page_main_feed',
            ['locale' => $locale]
        );
        $staticFile = $this->getStaticDir().'/'.$locale.'/feed.xml';
        $this->saveAsStatic($liveUri, $staticFile);

        if ($this->app->get('locale') == $locale) {
            $staticFile = $this->getStaticDir().'/feed.xml';
            $this->saveAsStatic($liveUri, $staticFile);
        }
    }

    protected function generateRobotsTxt($locale)
    {
        $liveUri = $this->generateLivePathFor(
            $this->app->getMainHost(),
            'pushword_page_robots_txt',
            ['locale' => $locale]
        );
        $staticFile = $this->getStaticDir().'/'.$locale.'/robots.txt';
        $this->saveAsStatic($liveUri, $staticFile);

        if ($this->app->get('locale') == $locale) {
            $staticFile = $this->getStaticDir().'/robots.txt';
            $this->saveAsStatic($liveUri, $staticFile);
        }
    }

    /**
     * The function cache redirection found during generatePages and
     * format in self::$redirection the content for the .htaccess.
     */
    protected function addRedirection($from, string $to = '', $code = 0)
    {
        $this->redirections .= 'Redirect ';
        $this->redirections .= $code ?: $from->getRedirectionCode();
        $this->redirections .= ' ';
        $this->redirections .= $from instanceof Page ? $this->router->generate($from->getRealSlug()) : $from;
        $this->redirections .= ' ';
        $this->redirections .= $to ?: $from->getRedirection();
        $this->redirections .= PHP_EOL;
    }

    protected function generatePages(): void
    {
        $pages = $this->getPageRepository()
            ->setHostCanBeNull($this->mustGetPagesWithoutHost)
            ->getPublishedPages($this->app->getMainHost());

        foreach ($pages as $page) {
            $this->generatePage($page);
            //if ($page->getRealSlug()) $this->generateFeedFor($page);
        }
    }

    protected function generatePage(Page $page)
    {
        // check if it's a redirection
        if (false !== $page->getRedirection()) {
            $this->addRedirection($page);

            return;
        }

        $this->saveAsStatic($this->generateLivePathFor($page), $this->generateFilePath($page));
    }

    protected function saveAsStatic($liveUri, $destination)
    {
        $request = Request::create($liveUri);

        $response = static::$appKernel->handle($request);

        if ($response->isRedirect()) {
            if (isset($response->headers['location'])) {
                $this->addRedirection($liveUri, $response->headers['location'], $response->getStatusCode());
            }

            return;
        } elseif (200 != $response->getStatusCode()) {
            //$this->kernel = static::$appKernel;
            if (500 === $response->getStatusCode() && 'dev' == $this->kernel->getEnvironment()) {
                exit($this->kernel->handle($request));
            }

            return;
        }

        if (false !== strpos($response->headers->all()['content-type'][0] ?? '', 'html')) {
            $content = $this->compress($response->getContent());
        } else {
            $content = $response->getContent();
        }

        $this->filesystem->dumpFile($destination, $content);
    }

    protected function compress($html)
    {
        return $this->parser->compress($html);
    }

    protected function generateFilePath(Page $page)
    {
        $slug = '' == $page->getRealSlug() ? 'index' : $page->getRealSlug();

        if (pathinfo($page->getRealSlug(), PATHINFO_EXTENSION)) {
            return $this->getStaticDir().'/'.$slug;
        }

        return $this->getStaticDir().'/'.$slug.'.html';
    }

    /**
     * Generate static file for feed indexing children pages
     * (only if children pages exists).
     *
     * @return void
     */
    protected function generateFeedFor(Page $page)
    {
        $liveUri = $this->generateLivePathFor($page, 'pushword_page_feed');
        $staticFile = preg_replace('/.html$/', '.xml', $this->generateFilePath($page));
        $this->saveAsStatic($liveUri, $staticFile);
    }

    protected function generateErrorPages(): void
    {
        $this->generateErrorPage();

        foreach ($this->app->getLocales() as $locale) {
            $this->filesystem->mkdir($this->getStaticDir().'/'.$locale);
            $this->generateErrorPage($locale);
        }
    }

    protected function generateErrorPage($locale = null, $uri = '404.html')
    {
        if (null !== $locale) {
            $request = new Request();
            $request->setLocale($locale);
            $this->requesStack->push($request);
        }

        $dump = $this->parser->compress($this->twig->render('@Twig/Exception/error.html.twig'));
        $this->filesystem->dumpFile($this->getStaticDir().(null !== $locale ? '/'.$locale : '').'/'.$uri, $dump);
    }

    protected function getPageRepository(): PageRepositoryInterface
    {
        return $this->pageRepository;
    }
}
