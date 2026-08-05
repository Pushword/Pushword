<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class ImageOptimizerCommandTest extends KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
    }

    public function testOptimizesASingleNamedMedia(): void
    {
        self::bootKernel();

        $commandTester = $this->runOptimizeCommand(['media' => 'piedweb-logo.png']);

        self::assertStringContainsString('peak memory', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testUnknownMediaNameOptimizesNothing(): void
    {
        self::bootKernel();

        $commandTester = $this->runOptimizeCommand(['media' => 'image-optimizer-does-not-exist.png']);

        self::assertStringContainsString('No media to optimize', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    /** @param array<string, mixed> $args */
    private function runOptimizeCommand(array $args): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:image:optimize'));
        $commandTester->execute($args);

        return $commandTester;
    }
}
