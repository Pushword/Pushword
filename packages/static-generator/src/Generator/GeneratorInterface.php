<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\StaticGenerator\StaticAppGenerator;

interface GeneratorInterface
{
    public function generate(string $host = null): void;

    public function setStaticAppGenerator(StaticAppGenerator $staticAppGenerator): self;
}
