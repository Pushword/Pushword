<?php

namespace Pushword\AdminBlockEditor\Block;

use Pushword\Core\Component\App\AppConfig;
use Twig\Environment as Twig;

interface BlockInterface
{
    /** @param mixed $value */
    public function render($data): string;

    public function setApp(AppConfig $app): self;

    public function getApp(): AppConfig;

    public function getEntity(): object;

    public function setEntity(object $entity): self;

    public function setTwig(Twig $twig): self;

    public function getTwig(): Twig;
}
