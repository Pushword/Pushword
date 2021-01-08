<?php

namespace Pushword\StaticGenerator;

use Pushword\Core\Component\App\AppPool;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generate 1 App.
 */
class StaticAppGenerator
{
    /**
     * @var AppPool
     */
    protected $apps;

    /** @var GeneratorBag */
    protected $generatorBag;

    /** @var RedirectionManager */
    protected $redirectionManager;

    public function __construct(
        AppPool $apps,
        GeneratorBag $generatorBag,
        RedirectionManager $redirectionManager
    ) {
        $this->apps = $apps;
        $this->generatorBag = $generatorBag;
        $this->redirectionManager = $redirectionManager;
    }

    /**
     * Undocumented function.
     *
     * @param string $filter
     *
     * @return int the number of site generated
     */
    public function generateAll(?string $filter = null): int
    {
        $i = 0;
        foreach ($this->apps->getHosts() as $host) {
            if ($filter && $filter != $host) {
                continue;
            }

            $this->generate($host);
            $this->redirectionManager->reset();
            ++$i;
        }

        return $i;
    }

    public function generateFromHost($host)
    {
        return $this->generateAll($host);
    }

    public function generatePage($host, string $page)
    {
        $this->apps->switchCurrentApp($host)->get();

        $this->generatorBag->get(PagesGenerator::class)->generatePageBySlug($page);
    }

    /**
     * Main Logic is here.
     *
     * @throws \RuntimeException
     * @throws \LogicException
     */
    protected function generate($host)
    {
        $app = $this->apps->switchCurrentApp($host)->get();

        $filesystem = new Filesystem();
        $filesystem->remove($app->get('static_dir'));
        $filesystem->mkdir($app->get('static_dir'));

        foreach ($app->get('static_generators') as $generator) {
            $this->generatorBag->get($generator)->generate();
        }
    }
}
