<?php

namespace Pushword\Core\Tests\Twig;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\SVGExtension;
use Pushword\Core\Utils\FontAwesome5To6;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class SVGExtensionTest extends KernelTestCase
{
    public function testFontAwesome5To6(): void
    {
        self::assertSame('file-lines', FontAwesome5To6::convertNameFromFontAwesome5To6('file-alt'));
    }

    public function testTwigExtension(): void
    {
        self::bootKernel();
        $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));
        self::assertStringStartsWith('<svg', $twig->getSvg('facebook'));
    }

    /**
     * The icon is read once, but the attributes are per call — a cached icon
     * must not carry the previous call's attributes.
     */
    public function testRepeatedIconKeepsItsOwnAttributes(): void
    {
        self::bootKernel();
        $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));

        $first = $twig->getSvg('facebook', ['class' => 'first-one']);
        $second = $twig->getSvg('facebook', ['class' => 'second-one']);

        self::assertStringContainsString('first-one', $first);
        self::assertStringNotContainsString('second-one', $first);

        self::assertStringContainsString('second-one', $second);
        self::assertStringNotContainsString('first-one', $second);
    }

    /**
     * `svg_dir` is per app, so the same name may resolve to a different file
     * depending on the host: the cache must not leak across search paths.
     */
    public function testSameNameInTwoDirsDoesNotLeak(): void
    {
        $dirOne = sys_get_temp_dir().'/pw-svg-one-'.getmypid();
        $dirTwo = sys_get_temp_dir().'/pw-svg-two-'.getmypid();
        foreach ([$dirOne => 'one', $dirTwo => 'two'] as $dir => $marker) {
            @mkdir($dir, 0o777, true);
            file_put_contents($dir.'/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" id="'.$marker.'"></svg>');
        }

        try {
            self::bootKernel();
            $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));

            self::assertStringContainsString('id="one"', $twig->getSvg('logo', [], $dirOne));
            self::assertStringContainsString('id="two"', $twig->getSvg('logo', [], $dirTwo));
        } finally {
            foreach ([$dirOne, $dirTwo] as $dir) {
                @unlink($dir.'/logo.svg');
                @rmdir($dir);
            }
        }
    }

    public function testUnknownIconFallsBackToQuestion(): void
    {
        self::bootKernel();
        $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));

        $fallback = $twig->getSvg('zzz-no-such-icon');
        self::assertStringStartsWith('<svg', $fallback);
        // cached fallback must stay a fallback, and not become the next icon
        self::assertSame($fallback, $twig->getSvg('zzz-no-such-icon'));
        self::assertStringStartsWith('<svg', $twig->getSvg('facebook'));
    }

    public function testNonSvgFileIsRejected(): void
    {
        $dir = sys_get_temp_dir().'/pw-svg-invalid-'.getmypid();
        @mkdir($dir, 0o777, true);
        file_put_contents($dir.'/not-really.svg', 'plain text, definitely not an svg');

        try {
            self::bootKernel();
            $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('seems not be a valid svg file');
            $twig->getSvg('not-really', [], $dir);
        } finally {
            @unlink($dir.'/not-really.svg');
            @rmdir($dir);
        }
    }

    /** With no icon at all, even the `question` fallback cannot resolve. */
    public function testMissingIconWithoutFallbackThrows(): void
    {
        $dir = sys_get_temp_dir().'/pw-svg-empty-'.getmypid();
        @mkdir($dir, 0o777, true);

        try {
            self::bootKernel();
            $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('`question` (svg) not found.');
            $twig->getSvg('zzz-no-such-icon', [], $dir);
        } finally {
            @rmdir($dir);
        }
    }

    public function testStringAttrMergesWithDefaultClasses(): void
    {
        self::bootKernel();
        $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));

        // no 'block' in the string: keep the defaults and append
        self::assertStringContainsString('fill-current w-4 inline-block -mt-1 text-red', $twig->getSvg('facebook', 'text-red'));

        // the string already sizes/displays the icon: it replaces the defaults
        $sized = $twig->getSvg('facebook', 'block w-8');
        self::assertStringContainsString('block w-8', $sized);
        self::assertStringNotContainsString('fill-current', $sized);
    }

    public function testFontAwesome5NameIsRenamedAndCached(): void
    {
        self::bootKernel();
        $twig = new SVGExtension(self::getContainer()->get(SiteRegistry::class));

        // 'file-alt' only exists under its FontAwesome 6 name, 'file-lines'
        $renamed = $twig->getSvg('file-alt');
        self::assertStringStartsWith('<svg', $renamed);
        self::assertSame($renamed, $twig->getSvg('file-alt'));
        self::assertSame($twig->getSvg('file-lines'), $renamed);
    }
}
