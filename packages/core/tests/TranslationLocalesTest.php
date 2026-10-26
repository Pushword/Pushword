<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Every locale a package ships carries the English keys, no more, no less: a key added
 * in English only shows its raw name to the sites running that locale.
 */
final class TranslationLocalesTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function translationFiles(): iterable
    {
        $packages = \dirname(__DIR__, 2);
        $englishFiles = new Finder()->files()->in($packages.'/*/src')->path('#^(Resources/)?translations/[^/]+\.en\.yaml$#');
        foreach ($englishFiles as $english) {
            $domain = $english->getBasename('.en.yaml');
            foreach (new Finder()->files()->in($english->getPath())->depth(0)->name($domain.'.*.yaml')->notName($english->getFilename()) as $file) {
                yield substr($file->getPathname(), \strlen($packages) + 1) => [$english->getPathname(), $file->getPathname()];
            }
        }
    }

    #[DataProvider('translationFiles')]
    public function testLocaleHasTheEnglishKeys(string $english, string $file): void
    {
        $expected = $this->keys(Yaml::parseFile($english));
        $actual = $this->keys(Yaml::parseFile($file));

        self::assertSame([], array_values(array_diff($expected, $actual)), 'Missing keys');
        self::assertSame([], array_values(array_diff($actual, $expected)), 'Keys English does not have');
    }

    /** @return string[] */
    private function keys(mixed $messages, string $prefix = ''): array
    {
        $keys = [];
        foreach (\is_array($messages) ? $messages : [] as $key => $value) {
            $keys = [...$keys, ...(\is_array($value) ? $this->keys($value, $prefix.$key.'.') : [$prefix.$key])];
        }

        return $keys;
    }
}
