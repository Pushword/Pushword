<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

abstract class AbstractGenerator implements GeneratorInterface
{
    use GenerateLivePathForTrait;

    use KernelTrait;

    protected Filesystem $filesystem;

    protected string $publicDir;

    protected SiteConfig $app;

    protected StaticAppGenerator $staticAppGenerator;

    public function __construct(
        protected PageRepository $pageRepository,
        protected Twig $twig,
        protected ParameterBagInterface $params,
        protected TranslatorInterface $translator,
        protected PushwordRouteGenerator $router,
        KernelInterface $kernel,
        protected SiteRegistry $apps,
    ) {
        $this->filesystem = new Filesystem();
        $this->router->setUseCustomHostPath(false);
        $this->publicDir = $params->get('pw.public_dir');

        static::loadKernel($kernel);
        $this->kernel = $kernel;

        $newKernelRouter = static::getKernel()->getContainer()->get(PushwordRouteGenerator::class);
        $newKernelRouter->setUseCustomHostPath(false);
    }

    public function generate(?string $host = null): void
    {
        $this->init($host);
    }

    protected function init(?string $host = null): void
    {
        $this->app = null !== $host ? $this->apps->switchSite($host)->get() : $this->apps->get();
    }

    /**
     * Symlink doesn't work on github page, symlink only for apache if conf say OK to symlink.
     */
    protected function mustSymlink(): bool
    {
        return $this->useGenerator(CNAMEGenerator::class) ? false
          : (bool) $this->app->get('static_symlink');
    }

    /**
     * @param class-string<GeneratorInterface> $generatorClass
     */
    protected function useGenerator(string $generatorClass): bool
    {
        $appGenerators = $this->app->getArray('static_generators');

        return in_array($generatorClass, $appGenerators, true);
    }

    protected function copy(string $file): void
    {
        if ($this->filesystem->exists($file)) {
            $this->filesystem->copy($this->publicDir.'/'.$file, $this->getStaticDir().'/'.$file);
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

    /**
     * Determine the fallback order for image formats based on configuration.
     *
     * @return array{webp_fallback: string[]}
     */
    protected function getImageFallbackOrder(): array
    {
        $filterSets = $this->params->get('pw.image_filter_sets');
        $allFormats = array_merge(...array_column($filterSets, 'formats'));

        return [
            'webp_fallback' => \in_array('original', $allFormats, true) ? ['original'] : [],
        ];
    }
}
