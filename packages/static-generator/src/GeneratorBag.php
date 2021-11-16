<?php

namespace Pushword\StaticGenerator;

use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RobotsGenerator;
use Symfony\Contracts\Service\Attribute\Required;

class GeneratorBag
{
    /** @required */
    public CNAMEGenerator $cNAMEGenerator;

    /** @required */
    public CopierGenerator $copierGenerator;

    /** @required */
    public ErrorPageGenerator $errorPageGenerator;

    /** @required */
    public HtaccessGenerator $htaccessGenerator;

    /** @required */
    public MediaGenerator $mediaGenerator;

    /** @required */
    public PagesGenerator $pagesGenerator;

    /** @required */
    public RobotsGenerator $robotsGenerator;

    /** @var array<string, GeneratorInterface> */
    private array $bag = [];

    public function __construct()
    {
    }

    protected function classNameToPropertyName(string $name): string
    {
        $name = explode('\\', $name);

        return lcfirst(end($name));
    }

    public function set(GeneratorInterface $generator): void
    {
        $name = $this->classNameToPropertyName(\get_class($generator));

        if (property_exists($this, $name)) {
            $this->$name = $generator; // @phpstan-ignore-line
        } else {
            $this->bag[$name] = $generator;
        }
    }

    public function get(string $name): GeneratorInterface
    {
        $name = $this->classNameToPropertyName($name);

        if (property_exists($this, $name)) {
            return $this->$name; // @phpstan-ignore-line
        }

        return $this->bag[$name];
    }
}
