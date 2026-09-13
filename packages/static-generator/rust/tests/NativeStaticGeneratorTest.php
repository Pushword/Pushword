<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Generator\AbstractGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\HtmlMinification;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final class NativeStaticGeneratorTest extends KernelTestCase
{
    public function testRealPublicationMatchesPhpIncludingLocalizedErrorPages(): void
    {
        $directory = sys_get_temp_dir().'/pushword-native-build-'.bin2hex(random_bytes(8));
        $native = new HtmlMinification(__DIR__.'/../target/release/pushword-html-minifier');

        try {
            $expected = $this->build($directory.'/php', new HtmlMinification());
            $actual = $this->build($directory.'/rust', $native);
            self::assertArrayHasKey('index.html', $expected);
            self::assertArrayHasKey('404.html', $expected);
            self::assertArrayHasKey('fr/404.html', $expected);
            self::assertSame(array_keys($expected), array_keys($actual));
            foreach ($expected as $path => $html) {
                if ($html !== $actual[$path]) {
                    $offset = 0;
                    $limit = min(strlen($html), strlen($actual[$path]));
                    while ($offset < $limit && $html[$offset] === $actual[$path][$offset]) {
                        ++$offset;
                    }

                    $start = max(0, $offset - 80);
                    self::fail($path.' differs at byte '.$offset."\nPHP: ".substr($html, $start, 160)."\nRust: ".substr($actual[$path], $start, 160));
                }

                self::assertSame($html, $actual[$path], $path);
            }
        } finally {
            $native->reset();
            new Filesystem()->remove($directory);
        }
    }

    /** @return array<string, string> */
    private function build(string $directory, HtmlMinification $minifier): array
    {
        // Each build starts with fresh entities and render services (gallery/quiz IDs).
        self::bootKernel();

        try {
            $container = self::getContainer();
            $site = $container->get(SiteRegistry::class)->switchSite('localhost.dev')->get();
            $site->setCustomProperty('cache', 'none');
            $site->setCustomProperty('static_dir', $directory);
            $container->get(PagesGenerator::class)->htmlMinification = $minifier;
            $container->get(ErrorPageGenerator::class)->htmlMinification = $minifier;
            $generator = $container->get(StaticAppGenerator::class);
            $generator->setWorkers(1);
            $generator->generate('localhost.dev');
            self::assertSame([], $generator->getErrors());

            return $this->htmlFiles($directory);
        } finally {
            AbstractGenerator::$appKernel?->shutdown();
            AbstractGenerator::$appKernel = null;
            self::ensureKernelShutdown();
        }
    }

    /** @return array<string, string> */
    private function htmlFiles(string $directory): array
    {
        $files = [];
        foreach (new Finder()->files()->name('*.html')->in($directory) as $file) {
            $files[$file->getRelativePathname()] = $file->getContents();
        }

        ksort($files);

        return $files;
    }
}
