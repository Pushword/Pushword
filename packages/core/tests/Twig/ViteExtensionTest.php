<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Twig\ViteExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment as Twig;

/**
 * The dev-app does not register pentatrion/vite-bundle, which is the situation of
 * any site not using Vite — so these cover the degraded path the front actually
 * takes there. The installed path needs the bundle and is out of reach in-repo.
 */
#[Group('integration')]
final class ViteExtensionTest extends KernelTestCase
{
    public function testStylesheetDegradesToAnHtmlCommentWithoutTheViteBundle(): void
    {
        self::assertSame(
            '<!--You must install vite bundle to use this function-->',
            $this->getExtension()->renderViteStylesheet(self::getContainer()->get(Twig::class), 'app'),
        );
    }

    public function testScriptDegradesToAnHtmlCommentWithoutTheViteBundle(): void
    {
        self::assertSame(
            '<!--You must install vite bundle to use this function-->',
            $this->getExtension()->renderViteScript(self::getContainer()->get(Twig::class), 'app'),
        );
    }

    public function testEntryListsNoFileWithoutTheViteBundle(): void
    {
        self::assertSame([], $this->getExtension()->getEntry('app'));
    }

    private function getExtension(): ViteExtension
    {
        self::bootKernel();

        return self::getContainer()->get(ViteExtension::class);
    }
}
