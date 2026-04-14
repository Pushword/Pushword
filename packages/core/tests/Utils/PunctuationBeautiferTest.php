<?php

namespace Pushword\Core\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\HtmlBeautifer;

class PunctuationBeautiferTest extends TestCase
{
    public function testIt(): void
    {
        $text = 'Mon valeur a testé <span class="bg-black !text-white">est ici</span> ! Avec une « nouvelle » phrase ?';
        $expected = 'Mon valeur a testé <span class="bg-black !text-white">est ici</span>&nbsp;! Avec une «&nbsp;nouvelle&nbsp;» phrase&nbsp;?';

        self::assertSame($expected, HtmlBeautifer::punctuationBeautifer($text));
    }
}
