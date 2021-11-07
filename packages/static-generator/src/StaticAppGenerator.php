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
     * @param string $host if null, generate all apps
     *
     * @return int the number of site generated
     */
    public function generate(?string $hostToGenerate = null): int
    {
        $i = 0;
        foreach ($this->apps->getHosts() as $host) {
            if ($hostToGenerate && $hostToGenerate != $host) {
                continue;
            }

            $this->generateHost($host);
            $this->redirectionManager->reset();
            ++$i;
        }

        return $i;
    }

    public function generatePage($host, string $page)
    {
        $this->apps->switchCurrentApp($host)->get();

        $this->generatorBag->get(PagesGenerator::class)->generatePageBySlug($page);
    }

    /**
     * @throws \RuntimeException
     * @throws \LogicException
     * @psalm-suppress  UndefinedPropertyAssignment
     */
    protected function generateHost(?string $host)
    {
        $app = $this->apps->switchCurrentApp($host)->get();

        $staticDir = $app->get('static_dir');
        $app->staticDir = $staticDir.'~';

        $filesystem = new Filesystem();
        $filesystem->remove($staticDir.'~');
        $filesystem->mkdir($staticDir.'~');

        foreach ($app->get('static_generators') as $generator) {
            //dump($generator);
            $this->generatorBag->get($generator)->generate();
        }

        $filesystem->remove($staticDir);
        $filesystem->rename($staticDir.'~', $staticDir);
        $filesystem->remove($staticDir.'~');
    }
}
