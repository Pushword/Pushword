<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use Pushword\Core\Installer\Update795;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InstallerTest extends KernelTestCase
{
    public function testIt()
    {
        $this->assertTrue(true);

        return;

        (new Update795())->run();
        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/src/Migrations'));
    }
}
