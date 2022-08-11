<?php

declare(strict_types=1);

namespace Pushword\Svg\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\Svg\FontAwesome5To6;

class FontAwesome5To6Test extends TestCase
{
    public function testIt(): void
    {
        $this->assertSame('file-lines', FontAwesome5To6::convertNameFromFontAwesome5To6('file-alt'));
    }
}
