<?php

namespace Pushword\Core\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MediaCacheGeneratorCommandTest extends KernelTestCase
{
    public function testExecute()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pushword:media:cache');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertTrue(false !== strpos($output, '100%'));

        $commandTester->execute(['media' => 'piedweb-logo.png']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertTrue(false !== strpos($output, '100%'));
    }
}
