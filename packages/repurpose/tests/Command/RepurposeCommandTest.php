<?php

namespace Pushword\Repurpose\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class RepurposeCommandTest extends KernelTestCase
{
    private function tester(string $name): CommandTester
    {
        $application = new Application(self::createKernel());

        return new CommandTester($application->find($name));
    }

    private function fileWith(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'pw-repurpose-');
        file_put_contents($path, $content);

        return $path;
    }

    private const string VALID = '{"page":"blog/x","network":"linkedin","format":"linkedin-4-5",'
        .'"slides":[{"title":"Hello","image":{"media":"photo.jpg"}}]}';

    public function testSchemaCommandPrintsJsonSchema(): void
    {
        $tester = $this->tester('pw:repurpose:schema');
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Pushword Repurpose Carousel', $display);
        self::assertStringContainsString('"slides"', $display);
        // Valid JSON.
        self::assertIsArray(json_decode($display, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testValidateReportsValidSpec(): void
    {
        $path = $this->fileWith(self::VALID);
        $tester = $this->tester('pw:repurpose:validate');
        $tester->execute(['path' => $path, '--format' => 'text']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('valid', $tester->getDisplay());
        unlink($path);
    }

    public function testValidateFailsWithPreciseViolations(): void
    {
        $path = $this->fileWith('{"page":"x","network":"instagram","format":"linkedin-4-5",'
            .'"slides":[{"title":"Hi"}]}');
        $tester = $this->tester('pw:repurpose:validate');
        $tester->execute(['path' => $path, '--format' => 'text']);

        self::assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('path', $display);
        self::assertStringContainsString('format', $display);
        unlink($path);
    }

    public function testValidateAgentOutputIsSingleJsonLine(): void
    {
        $path = $this->fileWith(self::VALID);
        $tester = $this->tester('pw:repurpose:validate');
        $tester->execute(['path' => $path, '--format' => 'agent']);

        $lines = array_values(array_filter(explode("\n", trim($tester->getDisplay())), static fn (string $l): bool => '' !== trim($l)));
        self::assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:repurpose:validate', $decoded['tool']);
        self::assertSame('passed', $decoded['result']);
        unlink($path);
    }

    public function testRenderWritesOneSvgPerSlide(): void
    {
        $path = $this->fileWith('{"page":"blog/x","network":"linkedin","format":"linkedin-4-5",'
            .'"slides":[{"title":"One"},{"title":"Two"}]}');
        $outDir = $this->tempDir();

        $tester = $this->tester('pw:repurpose:render');
        $tester->execute(['spec' => $path, 'out-dir' => $outDir, '--format' => 'text']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertFileExists($outDir.'/slide-1.svg');
        self::assertFileExists($outDir.'/slide-2.svg');
        self::assertStringContainsString('<svg', (string) file_get_contents($outDir.'/slide-1.svg'));

        unlink($path);
        $this->rmDir($outDir);
    }

    public function testRenderAgentOutputReportsWrittenFiles(): void
    {
        $path = $this->fileWith(self::VALID);
        $outDir = $this->tempDir();

        $tester = $this->tester('pw:repurpose:render');
        $tester->execute(['spec' => $path, 'out-dir' => $outDir, '--format' => 'agent']);

        $lines = array_values(array_filter(explode("\n", trim($tester->getDisplay())), static fn (string $l): bool => '' !== trim($l)));
        self::assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:repurpose:render', $decoded['tool']);
        self::assertSame('done', $decoded['result']);
        self::assertSame(1, $decoded['slides']);

        unlink($path);
        $this->rmDir($outDir);
    }

    public function testRenderFailsOnMalformedJson(): void
    {
        $path = $this->fileWith('{not json');
        $tester = $this->tester('pw:repurpose:render');
        $tester->execute(['spec' => $path, 'out-dir' => sys_get_temp_dir(), '--format' => 'text']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('JSON', $tester->getDisplay());
        unlink($path);
    }

    private function tempDir(): string
    {
        $dir = (string) tempnam(sys_get_temp_dir(), 'pw-render-');
        unlink($dir);
        mkdir($dir);

        return $dir;
    }

    private function rmDir(string $dir): void
    {
        array_map(unlink(...), glob($dir.'/*') ?: []);
        @rmdir($dir);
    }
}
