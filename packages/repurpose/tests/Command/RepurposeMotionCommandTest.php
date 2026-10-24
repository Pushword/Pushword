<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class RepurposeMotionCommandTest extends KernelTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pw-motion-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::createKernel())->find('pw:repurpose:motion'));
    }

    private function spec(string $json = '{"page":"x","network":"instagram","format":"story-9-16","creator":{"name":"Guest Author"},"slides":[{"title":"Hello"}]}'): string
    {
        $path = $this->directory.'/spec.json';
        file_put_contents($path, $json);

        return $path;
    }

    public function testExportsProjectAndReportsOnlyFilesActuallyWritten(): void
    {
        $tester = $this->tester();
        $out = $this->directory.'/video';
        $tester->execute(['spec' => $this->spec(), 'out-dir' => $out, '--format' => 'agent']);
        self::assertSame(0, $tester->getStatusCode());
        $lines = explode("\n", trim($tester->getDisplay()));
        self::assertCount(1, $lines);
        $result = json_decode($lines[0], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertSame('done', $result['result']);
        self::assertIsArray($result['files']);
        foreach ($result['files'] as $file) {
            self::assertIsString($file);
            self::assertFileExists($file);
        }

        self::assertFileExists($out.'/timeline.json');
        self::assertFileExists($out.'/src/scenes.jsx');
        self::assertStringContainsString('Guest Author', (string) file_get_contents($out.'/public/slides/slide-1/creator.svg'));
    }

    public function testExistingProjectIsNeverOverwritten(): void
    {
        $out = $this->directory.'/video';
        mkdir($out);
        file_put_contents($out.'/custom.jsx', 'precious work');
        $tester = $this->tester();
        $tester->execute(['spec' => $this->spec(), 'out-dir' => $out, '--format' => 'text']);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('already exists', $tester->getDisplay());
        self::assertSame('precious work', file_get_contents($out.'/custom.jsx'));
        self::assertFileDoesNotExist($out.'/timeline.json');
    }

    public function testInvalidSpecsFailBeforeWriting(): void
    {
        foreach (['{broken', '[]', '{"page":"x","network":"instagram","format":"bad","slides":[{"title":"Hi"}]}'] as $json) {
            $tester = $this->tester();
            $out = $this->directory.'/video';
            $tester->execute(['spec' => $this->spec($json), 'out-dir' => $out, '--format' => 'agent']);
            self::assertSame(1, $tester->getStatusCode());
            $result = json_decode(trim($tester->getDisplay()), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($result);
            self::assertSame('failed', $result['result']);
            self::assertNotEmpty($result['issues']);
            self::assertDirectoryDoesNotExist($out);
        }
    }

    public function testUnreadableSpecFailsWithoutCreatingAProject(): void
    {
        $tester = $this->tester();
        $tester->execute(['spec' => $this->directory.'/missing.json', 'out-dir' => $this->directory.'/video', '--format' => 'agent']);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Cannot read', $tester->getDisplay());
        self::assertDirectoryDoesNotExist($this->directory.'/video');
    }

    public function testWriteFailureIsReportedAsAgentJson(): void
    {
        $parent = $this->directory.'/file';
        file_put_contents($parent, 'keep');
        $tester = $this->tester();
        $tester->execute(['spec' => $this->spec(), 'out-dir' => $parent.'/video', '--format' => 'agent']);
        self::assertSame(1, $tester->getStatusCode());
        $result = json_decode(trim($tester->getDisplay()), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertSame('failed', $result['result']);
        self::assertNotEmpty($result['issues']);
        self::assertSame('keep', file_get_contents($parent));
    }
}
