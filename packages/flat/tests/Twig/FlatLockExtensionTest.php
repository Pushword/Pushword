<?php

namespace Pushword\Flat\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The functions are declared with #[AsTwigFunction], so the container reaches
 * them through autoconfiguration alone — an explicit `twig.extension` tag sends
 * the class to addExtension(), which only takes an ExtensionInterface and fails
 * the whole compilation. Nothing else covers the registration: the one template
 * calling flat_lock_info() is included with `ignore missing`.
 */
#[Group('integration')]
final class FlatLockExtensionTest extends KernelTestCase
{
    private function twig(): Environment
    {
        return self::getContainer()->get('twig');
    }

    public function testTheLockFunctionsAreRegisteredWithTwig(): void
    {
        foreach (['flat_lock_info', 'is_webhook_locked', 'is_flat_locked'] as $function) {
            self::assertNotNull($this->twig()->getFunction($function), $function.' is not registered');
        }
    }

    public function testAnUnlockedHostRendersNoBanner(): void
    {
        $rendered = $this->twig()
            ->createTemplate('{{ is_flat_locked("localhost.dev") ? "locked" : "free" }}')
            ->render();

        self::assertSame('free', $rendered);
    }
}
