<?php

namespace Pushword\Core\Tests\Command;

use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class SchemaDumpCommandTest extends KernelTestCase
{
    public function testAgentFormatDumpsDeclaredPropertiesPerHost(): void
    {
        $json = $this->decode($this->execute(['--format' => 'agent']));

        self::assertSame('pw:schema:dump', $json['tool']);
        self::assertSame('done', $json['result']);

        $hosts = self::arr($json['hosts']);
        $devApp = self::arr($hosts['localhost.dev']);
        $pageProperties = self::arr($devApp['page_properties']);

        self::assertSame('int', self::arr($pageProperties['priority'])['type']);
        self::assertSame([['Choice' => ['choices' => ['beginner', 'advanced']]]], self::arr($pageProperties['level'])['constraints']);
        self::assertArrayHasKey('subtitle', $pageProperties, 'root-declared property falls through');
        self::assertSame('bool', self::arr($pageProperties['toc'])['type'], 'bundle-declared property is listed');
        self::assertContains('ogTitle', self::arr($devApp['managed_keys']));
        self::assertContains('slug', self::arr($devApp['frontmatter_columns']));

        self::assertArrayHasKey('pushword.piedweb.com', $hosts);
        $piedweb = self::arr(self::arr($hosts['pushword.piedweb.com'])['page_properties']);
        self::assertArrayNotHasKey('priority', $piedweb, 'app declarations stay per host');
        self::assertArrayHasKey('subtitle', $piedweb, 'root declarations reach every host');
    }

    public function testHostOptionLimitsTheDump(): void
    {
        $json = $this->decode($this->execute(['--format' => 'agent', '--host' => 'localhost.dev']));

        self::assertSame(['localhost.dev'], array_keys(self::arr($json['hosts'])));
    }

    public function testTextFormat(): void
    {
        $display = $this->execute(['--format' => 'text', '--host' => 'localhost.dev'])->getDisplay();

        self::assertStringContainsString('localhost.dev', $display);
        self::assertStringContainsString('priority', $display);
        self::assertStringContainsString('Managed keys', $display);
    }

    /** @param array<string, string> $options */
    private function execute(array $options): CommandTester
    {
        self::bootKernel();
        $command = new Application(self::$kernel ?? throw new LogicException())->find('pw:schema:dump');
        $commandTester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $commandTester->execute($options));

        return $commandTester;
    }

    /** @return array<string, mixed> */
    private function decode(CommandTester $commandTester): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** @return array<string, mixed> */
    private static function arr(mixed $value): array
    {
        self::assertIsArray($value);

        /** @var array<string, mixed> $value */
        return $value;
    }
}
