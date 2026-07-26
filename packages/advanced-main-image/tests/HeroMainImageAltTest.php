<?php

namespace Pushword\AdvancedMainImage\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Tests\MainImageAltTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class HeroMainImageAltTest extends KernelTestCase
{
    use MainImageAltTrait;

    protected function mainImageTemplate(): string
    {
        return '@PushwordAdvancedMainImage/page/_content_hero.html.twig';
    }
}
