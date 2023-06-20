<?php

namespace Pushword\AdminBlockEditor\Block;

use Pushword\Core\Component\App\AppConfig;
use Twig\Environment as Twig;

interface BlockInterface
{
    public function render(object $block): string;

    public function setApp(AppConfig $appConfig): self;

    public function getApp(): AppConfig;

    public function getEntity(): object;

    public function setEntity(object $entity): self;

    public function setTwig(Twig $twig): self;

    public function getTwig(): Twig;
}
