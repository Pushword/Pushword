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

class GeneratorBag
{
    #[\Symfony\Contracts\Service\Attribute\Required]
    public CNAMEGenerator $cNAMEGenerator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public CopierGenerator $copierGenerator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public ErrorPageGenerator $errorPageGenerator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public HtaccessGenerator $htaccessGenerator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public MediaGenerator $mediaGenerator;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public PagesGenerator $pagesGenerator;

    #[\Symfony\Contracts\Service\Attribute\Required]
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

        if (property_exists($this, $name)) {
            return $this->$name; // @phpstan-ignore-line
        }

        return $this->bag[$name];
    }
}
