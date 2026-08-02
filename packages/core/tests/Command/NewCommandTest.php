<?php

namespace Pushword\Core\Tests\Command;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Command\NewCommand;

use function Safe\file_get_contents;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Driven with a throwaway project dir: the container wires the real one, and the
 * command rewrites config/packages/pushword.yaml in place.
 */
final class NewCommandTest extends TestCase
{
    private string $projectDir;

    private string $configFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = sys_get_temp_dir().'/pw-new-test-'.uniqid();
        $this->configFile = $this->projectDir.'/config/packages/pushword.yaml';
        new Filesystem()->mkdir($this->projectDir.'/config/packages');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
        parent::tearDown();
    }

    public function testCreatesTheConfigFileWhenThereIsNone(): void
    {
        $commandTester = $this->runNewCommand(['example.test', 'en|fr']);

        self::assertStringContainsString('Config updated with success', $commandTester->getDisplay());
        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        self::assertSame(
            [['hosts' => ['example.test'], 'locale' => 'en', 'locales' => 'en|fr']],
            $this->configuredApps()
        );
    }

    public function testFallsBackToTheDefaultAnswers(): void
    {
        $this->runNewCommand(['', '']);

        self::assertSame(
            [['hosts' => ['localhost.dev'], 'locale' => 'en', 'locales' => 'en|fr']],
            $this->configuredApps()
        );
    }

    public function testAppendsToAnExistingConfig(): void
    {
        new Filesystem()->dumpFile($this->configFile, Yaml::dump([
            'pushword' => ['apps' => [['hosts' => ['first.test'], 'locale' => 'fr', 'locales' => 'fr']]],
        ], 4));

        $this->runNewCommand(['second.test', 'en']);

        $apps = $this->configuredApps();
        self::assertCount(2, $apps);
        self::assertSame(['first.test'], $apps[0]['hosts'] ?? null);
        self::assertSame(['second.test'], $apps[1]['hosts'] ?? null);
    }

    /** @param list<string> $inputs */
    private function runNewCommand(array $inputs): CommandTester
    {
        $command = new Command('pw:new');
        $command->setCode(new NewCommand($this->projectDir));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute([]);

        return $commandTester;
    }

    /** @return array<int, array<string, mixed>> */
    private function configuredApps(): array
    {
        /** @var array{pushword: array{apps: array<int, array<string, mixed>>}} $config */
        $config = Yaml::parse(file_get_contents($this->configFile));

        return $config['pushword']['apps'];
    }
}
