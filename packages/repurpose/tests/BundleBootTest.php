<?php

namespace Pushword\Repurpose\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke test: the package autoloads, the bundle is registered in the skeleton
 * app and the kernel boots with it enabled.
 */
final class BundleBootTest extends KernelTestCase
{
    public function testBundleIsRegistered(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('PushwordRepurposeBundle', $kernel->getBundles());
    }
}
