<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class StaticOutputLinterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pushword-static-linter-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/nested', recursive: true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testInvalidUtf8IsReportedWithoutConfiguredHosts(): void
    {
        file_put_contents($this->directory.'/valid.html', '<p>café</p>');
        file_put_contents($this->directory.'/nested/invalid.html', "<p>caf\xA9</p>");

        self::assertSame(
            ['Invalid UTF-8 in nested/invalid.html'],
            StaticOutputLinter::lint($this->directory, []),
        );
    }
}
