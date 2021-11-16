<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface as Page;
use Pushword\Core\Repository\PageRepositoryInterface;
use Pushword\Core\Router\RouterInterface;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;
use WyriHaximus\HtmlCompress\Factory as HtmlCompressor;
use WyriHaximus\HtmlCompress\HtmlCompressorInterface;

abstract class AbstractGenerator implements GeneratorInterface
{
    use GenerateLivePathForTrait;
    use KernelTrait;

    protected PageRepositoryInterface $pageRepository;

    protected Filesystem $filesystem;

    protected Twig $twig;

    protected string $publicDir;

    protected AppPool $apps;

    protected AppConfig $app;

    protected string $staticDir;

    protected RequestStack $requestStack;

    protected TranslatorInterface $translator;

    protected HtmlCompressorInterface $parser;

    protected ParameterBagInterface $params;

    protected RouterInterface $router;

    protected StaticAppGenerator $staticAppGenerator;

    public function __construct(
        PageRepositoryInterface $pageRepository,
        Twig $twig,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        RouterInterface $router,
        KernelInterface $kernel,
        AppPool $appPool
    ) {
        $this->pageRepository = $pageRepository;
        $this->filesystem = new Filesystem();
        $this->twig = $twig;
        $this->params = $parameterBag;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->router = $router;
        $this->router->setUseCustomHostPath(false);
        $this->apps = $appPool;
        $this->publicDir = \strval($parameterBag->get('pw.public_dir'));
        $this->parser = HtmlCompressor::construct();

        static::loadKernel($kernel);
        $this->kernel = $kernel;

        $newKernelRouter = static::getKernel()->getContainer()->get('pushword.router');
        $newKernelRouter->setUseCustomHostPath(false);
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
        return \is_array($this->app->get('static_generators'))
            && \in_array(CNAMEGenerator::class, $this->app->get('static_generators'), true) ? false
            : \boolval($this->app->get('static_symlink'));
    }

    protected function copy(string $file): void
    {
        if (file_exists($file)) {
            \Safe\copy(
                str_replace(\strval($this->params->get('kernel.project_dir')).'/', '../', $this->publicDir.'/'.$file),
                $this->getStaticDir().'/'.$file
            );
        }
    }

    protected function getStaticDir(): string
    {
        return \strval($this->app->get('static_dir'));
    }

    protected function getPageRepository(): PageRepositoryInterface
    {
        return $this->pageRepository;
    }

    public function setStaticAppGenerator(StaticAppGenerator $staticAppGenerator): self
    {
        $this->staticAppGenerator = $staticAppGenerator;

        return $this;
    }

    protected function setError(string $errorMessage): void
    {
        $this->staticAppGenerator->setError($errorMessage);
    }
}
