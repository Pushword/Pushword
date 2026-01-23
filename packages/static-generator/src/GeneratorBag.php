<?php

namespace Pushword\StaticGenerator;

use Pushword\StaticGenerator\Generator\CaddyfileGenerator;
use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesCompressor;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use Pushword\StaticGenerator\Generator\RobotsGenerator;

class GeneratorBag
{
    /** @var array<string, GeneratorInterface> */
    private array $bag = [];

    public function __construct(
        private readonly CNAMEGenerator $cNAMEGenerator,
        private readonly CopierGenerator $copierGenerator,
        private readonly ErrorPageGenerator $errorPageGenerator,
        private readonly HtaccessGenerator $htaccessGenerator,
        private readonly CaddyfileGenerator $caddyfileGenerator,
        private readonly MediaGenerator $mediaGenerator,
        private readonly PagesGenerator $pagesGenerator,
        private readonly RobotsGenerator $robotsGenerator,
        private readonly RedirectionManager $redirectionManager,
        private readonly PagesCompressor $pagesCompressor,
    ) {
    }

    protected function classNameToPropertyName(string $name): string
    {
        $name = explode('\\', $name);

        return lcfirst(end($name));
    }

    public function set(GeneratorInterface $generator): void
    {
        $name = $this->classNameToPropertyName($generator::class);

        if (property_exists($this, $name)) {
            $this->$name = $generator; // @phpstan-ignore-line
        } else {
            $this->bag[$name] = $generator;
        }
    }

    public function get(string $name): GeneratorInterface
    {
        $name = $this->classNameToPropertyName($name);

        if (property_exists($this, $name)
            && ($generator = $this->$name) instanceof GeneratorInterface) {  // @phpstan-ignore-line
            return $generator;
        }

        return $this->bag[$name];
    }
}
