<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use Pushword\AdminBlockEditor\EditorJsHelper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EditorJsHelperTest extends KernelTestCase
{
    public function testIt()
    {
        $text = " <a href=\"#test\">test\u{a0}</A>test";
        $expected = '<a href="#test">test</A> test';

        $this->assertSame($expected, EditorJsHelper::htmlPurifier($text));
    }
}
