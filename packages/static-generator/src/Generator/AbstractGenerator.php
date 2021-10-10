<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface as Page;
use Pushword\Core\Repository\PageRepositoryInterface;
use Pushword\Core\Router\RouterInterface;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;
use WyriHaximus\HtmlCompress\Factory as HtmlCompressor;
use WyriHaximus\HtmlCompress\HtmlCompressorInterface;

abstract class AbstractGenerator
{
    use GenerateLivePathForTrait;
    use KernelTrait;

    protected PageRepositoryInterface $pageRepository;

    protected Filesystem $filesystem;

    protected Twig $twig;

    protected string $publicDir;

    protected AppPool $apps;

    protected AppConfig $app;

    protected $staticDomain;

    /** @var string */
    protected $staticDir;

    protected RequestStack $requestStack;

    protected TranslatorInterface $translator;

    protected HtmlCompressorInterface $parser;

    protected ParameterBagInterface $params;

    protected RouterInterface $router;

    public function __construct(
        PageRepositoryInterface $pageRepository,
        Twig $twig,
        ParameterBagInterface $params,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        RouterInterface $router,
        KernelInterface $kernel,
        AppPool $apps
    ) {
        $this->pageRepository = $pageRepository;
        $this->filesystem = new Filesystem();
        $this->twig = $twig;
        $this->params = $params;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->router = $router;
        $this->router->setUseCustomHostPath(false);
        $this->apps = $apps;
        $this->publicDir = (string) $params->get('pw.public_dir');
        $this->parser = HtmlCompressor::construct();

        if (! method_exists($this->filesystem, 'dumpFile')) {
            throw new \RuntimeException('Method dumpFile() is not available. Upgrade your Filesystem.');
        }

        static::loadKernel($kernel);
        $this->kernel = $kernel;
        static::$appKernel->getContainer()->get('pushword.router')->setUseCustomHostPath(false);
    }

    public function generate(?string $host = null): void
    {
        $this->init($host);
    }

    protected function init(?string $host = null): void
    {
        $this->app = null !== $host ? $this->apps->switchCurrentApp($host)->get() : $this->apps->get();
    }

    /**
     * Symlink doesn't work on github page, symlink only for apache if conf say OK to symlink.
     */
    protected function mustSymlink(): bool
    {
        return \in_array(CNAMEGenerator::class, $this->app->get('static_generators')) ? false
            : $this->app->get('static_symlink');
    }

    protected function copy(string $file): void
    {
        if (file_exists($file)) {
            copy(
                str_replace($this->params->get('kernel.project_dir').'/', '../', $this->publicDir.'/'.$file),
                $this->getStaticDir().'/'.$file
            );
        }
    }

    protected function getStaticDir(): string
    {
        return $this->app->get('static_dir');
    }

    protected function getPageRepository(): PageRepositoryInterface
    {
        return $this->pageRepository;
    }
}
