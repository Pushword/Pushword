<?php

namespace Pushword\Core\Tests\Service;

use Pushword\Core\Twig\StringToSearch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AppExtensionTest extends KernelTestCase
{
    public function testStringToSearch()
    {
        $this->assertSame([['mainContent', 'LIKE', '%<!--blog-->%']], (new StringToSearch('comment:blog', null))->retrieve());
        $this->assertSame(
            [['slug', 'LIKE', 'blog'], 'OR', ['mainContent', 'LIKE', '%a%']],
            (new StringToSearch('slug:blog OR a', null))->retrieve()
        );
    }
}
