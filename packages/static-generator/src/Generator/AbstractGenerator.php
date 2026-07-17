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

    /**
     * The render kernel's own PushwordRouteGenerator — the instance that page()
     * resolves through while saveAsStatic() renders the HTML.
     */
    protected PushwordRouteGenerator $renderRouter;

    private ?string $staticDirOverride = null;

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
        // Defensive: this kernel's router is used in-process by RedirectionManager
        // to compute the static redirect map, whose "from" paths must stay host-less
        // (no /{host}/ prefix). mayUseCustomPath() already forces that here (it is
        // called with no host argument and no current page), so this is belt-and-
        // suspenders — but PushwordRouteGenerator::reset() still restores the flag
        // per request so a synchronous cache-mode regen never leaks it into a live one.
        // Deliberately NOT pinned: this kernel serves live traffic.
        $this->router->setUseCustomHostPath(false);
        $this->publicDir = $params->get('pw.public_dir');

        static::loadKernel($kernel);
        $this->kernel = $kernel;

        // The sub-kernel renders the page HTML itself (saveAsStatic → getKernel()->handle());
        // its own router must likewise drop the prefix. Distinct instance, not a duplicate.
        // PINNED, because a plain set cannot survive rendering: each handle() marks
        // services for reset, so from the second render on, the kernel's boot() runs
        // the services_resetter — inside handle(), after any pre-render re-assert —
        // and reset() would restore the /{host}/ prefix. Only the first page each
        // process rendered came out host-less; every later one linked to
        // /{host}/{slug}, which a brand static host serves as a 404. This kernel
        // never serves live traffic, so pinning is safe.
        $this->renderRouter = static::getKernel()->getContainer()->get(PushwordRouteGenerator::class);
        $this->renderRouter->setUseCustomHostPath(false, pin: true);

        foreach ($this->apps->getAll() as $site) {
            $site->isStatic = true;
        }

        $newKernelSiteRegistry = static::getKernel()->getContainer()->get(SiteRegistry::class);
        foreach ($newKernelSiteRegistry->getAll() as $site) {
            $site->isStatic = true;
        }
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
     * @return bool|string[]
     */
    private function getSymlinkConfig(): bool|array
    {
        if ($this->useGenerator(CNAMEGenerator::class)) {
            return false;
        }

        /** @var bool|string[] $config */
        $config = $this->app->get('static_symlink') ?? true;

        return $config;
    }

    protected function mustSymlinkMedia(): bool
    {
        $config = $this->getSymlinkConfig();

        return \is_array($config) ? \in_array('media', $config, true) : $config;
    }

    protected function mustSymlinkAssets(): bool
    {
        $config = $this->getSymlinkConfig();

        return \is_array($config) ? \in_array('assets', $config, true) : $config;
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

    public function setStaticDirOverride(string $dir): void
    {
        $this->staticDirOverride = $dir;
    }

    protected function getStaticDir(): string
    {
        return $this->staticDirOverride ?? $this->app->getStr('static_dir');
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
