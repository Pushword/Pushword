<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Tests\MainImageAltTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class MainImageAltTest extends KernelTestCase
{
    use MainImageAltTrait;

    protected function mainImageTemplate(): string
    {
        return '@PushwordCore/page/_content.html.twig';
    }
}
