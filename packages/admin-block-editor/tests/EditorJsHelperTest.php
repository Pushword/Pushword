<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use Pushword\AdminBlockEditor\EditorJsPurifier;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EditorJsHelperTest extends KernelTestCase
{
    public function testIt()
    {
        $text = " <a href=\"#test\">test\u{a0}</A>test : test";
        $expected = '<a href="#test">test</a> test&nbsp;: test';

        $this->assertSame($expected, (new EditorJsPurifier('fr'))->htmlPurifier($text));
    }
}
