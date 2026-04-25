<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Generator;

use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pushword.static_generator')]
interface GeneratorInterface
{
    public function generate(?string $host = null): void;

    public function setStaticAppGenerator(StaticAppGenerator $staticAppGenerator): self;
}
