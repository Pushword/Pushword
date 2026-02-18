<?php

namespace Pushword\Core\Tests\DependencyInjection;

use App\Kernel;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\FlysystemBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\MediaStorageAdapter;
use ReflectionProperty;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Verifies that app-level flysystem config (e.g. SFTP in when@dev)
 * can override the bundle's default local storage for pushword.mediaStorage.
 *
 * Bug: ExtensionTrait::prepend() used file loaders that append configs,
 * so the bundle's local adapter ended up after (and overrode) the app's SFTP config.
 * Fix: newly loaded configs are moved to the front of the extension config array.
 */
#[Group('integration')]
class FlysystemConfigOverrideTest extends TestCase
{
    private const array APP_SFTP_CONFIG = [
        'storages' => [
            'pushword.mediaStorage' => [
                'adapter' => 'sftp',
                'options' => [
                    'host' => '51.255.197.86',
                    'port' => 49154,
                    'username' => 'ubuntu',
                    'root' => '/home/ubuntu/media',
                ],
            ],
        ],
    ];

    private const array BUNDLE_LOCAL_CONFIG = [
        'storages' => [
            'pushword.mediaStorage' => [
                'adapter' => 'local',
                'options' => [
                    'directory' => '/var/www/media',
                ],
            ],
        ],
    ];

    /**
     * When bundle config is appended AFTER app config, the bundle wins.
     * This was the bug before the fix.
     */
    public function testBundleAppendAfterAppCausesBug(): void
    {
        $adapter = $this->resolveAdapter(
            first: self::APP_SFTP_CONFIG,
            second: self::BUNDLE_LOCAL_CONFIG,
        );

        self::assertSame(
            'local',
            $adapter,
            'When bundle appends after app, bundle wins (the bug scenario)'
        );
    }

    /**
     * When bundle config is prepended BEFORE app config, the app wins.
     * This is the correct behavior after the fix.
     */
    public function testBundlePrependBeforeAppAllowsOverride(): void
    {
        $adapter = $this->resolveAdapter(
            first: self::BUNDLE_LOCAL_CONFIG,
            second: self::APP_SFTP_CONFIG,
        );

        self::assertSame(
            'sftp',
            $adapter,
            'When bundle config is first and app config is second, app wins'
        );
    }

    /**
     * Boot the real kernel and verify pushword.mediaStorage uses local adapter
     * (baseline — no app-level override in skeleton).
     */
    public function testRealKernelUsesLocalByDefault(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        /** @var ContainerInterface $testContainer */
        $testContainer = $kernel->getContainer()->get('test.service_container'); // @phpstan-ignore symfonyContainer.serviceNotFound

        /** @var MediaStorageAdapter $storageAdapter */
        $storageAdapter = $testContainer->get(MediaStorageAdapter::class); // @phpstan-ignore symfonyContainer.privateService

        $storageRefl = new ReflectionProperty($storageAdapter, 'storage');
        /** @var Filesystem $storage */
        $storage = $storageRefl->getValue($storageAdapter);

        $adapterRefl = new ReflectionProperty($storage, 'adapter');
        $adapter = $adapterRefl->getValue($storage);

        self::assertInstanceOf(LocalFilesystemAdapter::class, $adapter);

        $kernel->shutdown();
    }

    /**
     * Simulate Symfony config merge: configs are processed in array order,
     * later entries override earlier ones for scalar values.
     *
     * @param array<string, mixed> $first  Config added first (lower priority)
     * @param array<string, mixed> $second Config added second (higher priority)
     */
    private function resolveAdapter(array $first, array $second): string
    {
        $configs = [$first, $second];
        $processed = new Processor()->processConfiguration(new Configuration(), $configs);

        /** @var array{storages: array<string, array{adapter: string}>} $processed */

        return $processed['storages']['pushword.mediaStorage']['adapter'];
    }
}
