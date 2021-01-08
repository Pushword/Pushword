<?php

namespace Pushword\StaticGenerator;

use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
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

    public function __construct()
    {
    }

    protected function classNameToPropertyName($name)
    {
        $name = explode('\\', $name);

        return lcfirst(end($name));
    }

    public function set($generator): void
    {
        $name = $this->classNameToPropertyName(\get_class($generator));

        $this->$name = $generator;
    }

    public function get(string $name)
    {
        $name = $this->classNameToPropertyName($name);

        return $this->$name;
    }
}
