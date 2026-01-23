<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Pushword\StaticGenerator\StaticAppGenerator;

use function Safe\copy;

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

    protected AppConfig $app;

    protected StaticAppGenerator $staticAppGenerator;

    public function __construct(
        protected PageRepository $pageRepository,
        protected Twig $twig,
        protected ParameterBagInterface $params,
        protected TranslatorInterface $translator,
        protected PushwordRouteGenerator $router,
        KernelInterface $kernel,
        protected AppPool $apps,
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
        $this->app = null !== $host ? $this->apps->switchCurrentApp($host)->get() : $this->apps->get();
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
        if (file_exists($file)) {
            copy(
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

    /**
     * Determine the fallback order for image formats based on configuration.
     * Returns the formats that should be tried in order (avif, webp, original).
     *
     * @return array{webp_fallback: string[], avif_fallback: string[]}
     */
    protected function getImageFallbackOrder(): array
    {
        $filterSets = $this->params->get('pw.image_filter_sets');

        // Check what formats are commonly configured across filters
        $hasAvif = false;
        $hasWebp = false;
        $hasOriginal = false;

        foreach ($filterSets as $filter) {
            /** @var string[] $formats */
            $formats = $filter['formats'];
            if (\in_array('avif', $formats, true)) {
                $hasAvif = true;
            }

            if (\in_array('webp', $formats, true)) {
                $hasWebp = true;
            }

            if (\in_array('original', $formats, true)) {
                $hasOriginal = true;
            }
        }

        // Determine fallback chain for each format
        // If webp is requested but doesn't exist: try avif, then original
        $webpFallback = [];
        if ($hasAvif) {
            $webpFallback[] = 'avif';
        }

        if ($hasOriginal) {
            $webpFallback[] = 'original';
        }

        // If avif is requested but doesn't exist: try webp, then original
        $avifFallback = [];
        if ($hasWebp) {
            $avifFallback[] = 'webp';
        }

        if ($hasOriginal) {
            $avifFallback[] = 'original';
        }

        return [
            'webp_fallback' => $webpFallback,
            'avif_fallback' => $avifFallback,
        ];
    }
}
