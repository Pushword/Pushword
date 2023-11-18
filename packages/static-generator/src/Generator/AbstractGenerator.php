<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
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

    protected Filesystem $filesystem;

    protected string $publicDir;

    protected AppConfig $app;

    protected string $staticDir;

    protected HtmlCompressorInterface $parser;

    protected StaticAppGenerator $staticAppGenerator;

    public function __construct(
        protected PageRepository $pageRepository,
        protected Twig $twig,
        protected ParameterBagInterface $params,
        protected RequestStack $requestStack,
        protected TranslatorInterface $translator,
        protected PushwordRouteGenerator $router,
        KernelInterface $kernel,
        protected AppPool $apps
    ) {
        $this->filesystem = new Filesystem();
        $this->router->setUseCustomHostPath(false);
        $this->publicDir = $params->get('pw.public_dir');
        $this->parser = HtmlCompressor::construct();

        static::loadKernel($kernel);
        $this->kernel = $kernel;

        $newKernelRouter = static::getKernel()->getContainer()->get(\Pushword\Core\Router\PushwordRouteGenerator::class);
        $newKernelRouter->setUseCustomHostPath(false);
    }

    public function generate(string $host = null): void
    {
        $this->init($host);
    }

    protected function init(string $host = null): void
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
            : (bool) $this->app->get('static_symlink');
    }

    protected function copy(string $file): void
    {
        if (file_exists($file)) {
            \Safe\copy(
                str_replace($this->params->get('kernel.project_dir').'/', '../', $this->publicDir.'/'.$file),
                $this->getStaticDir().'/'.$file
            );
        }
    }

    protected function getStaticDir(): string
    {
        return $this->app->getStr('static_dir');
    }

    protected function getPageRepository(): PageRepository
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
