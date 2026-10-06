<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\PHPStan;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Pushword\PHPStan\FrameworkUsageProvider;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class FrameworkUsageProviderTest extends TestCase
{
    #[DataProvider('methods')]
    public function testRecognizesFrameworkCallbacksWithoutMarkingOtherMethodsAsUsed(string $method, bool $used): void
    {
        $provider = new FrameworkUsageProvider(__DIR__.'/fixtures/container.xml');

        $usageCheck = new ReflectionMethod($provider, 'shouldMarkMethodAsUsed');

        foreach (new ReflectionClass(FrameworkUsageProviderFixture::class)->getMethods() as $reflection) {
            if ($method === $reflection->getName()) {
                self::assertSame($used, null !== $usageCheck->invoke($provider, $reflection));

                return;
            }
        }

        self::fail('Unknown fixture method: '.$method);
    }

    public function testTraitContractsAreRecognizedWithoutMarkingOrdinaryPropertiesAsUsed(): void
    {
        $provider = new FrameworkUsageProvider(__DIR__.'/fixtures/container.xml');
        $contract = new ReflectionProperty(GenerateLivePathForTrait::class, 'router');
        $ordinary = new ReflectionProperty(KernelTrait::class, 'debugKernel');
        $readCheck = new ReflectionMethod($provider, 'shouldMarkPropertyAsRead');
        $writeCheck = new ReflectionMethod($provider, 'shouldMarkPropertyAsWritten');

        self::assertNotNull($readCheck->invoke($provider, $contract));
        self::assertNotNull($writeCheck->invoke($provider, $contract));
        self::assertNull($readCheck->invoke($provider, $ordinary));
        self::assertNull($writeCheck->invoke($provider, $ordinary));
    }

    /** @return Iterator<string, array{string, bool}> */
    public static function methods(): Iterator
    {
        yield 'EasyAdmin route' => ['routed', true];
        yield 'protected route is not callable' => ['hidden', false];
        yield 'explicit kernel listener' => ['kernelExplicit', true];
        yield 'conventional kernel listener' => ['onSampleReady', true];
        yield 'invokable listener fallback' => ['__invoke', true];
        yield 'conventional Doctrine listener' => ['postPersist', true];
        yield 'explicit entity listener' => ['entityWritten', true];
        yield 'unrelated tag and method' => ['unrelated', false];
    }
}
