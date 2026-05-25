<?php

namespace Pushword\Search\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Event\StaticPostGenerateEvent;

use function Safe\json_decode;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('integration')]
final class StaticSearchSubscriberTest extends KernelTestCase
{
    public function testEmitsSearchJsonAndIndexDb(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $app = $container->get(SiteRegistry::class)->get('localhost.dev');

        $dir = sys_get_temp_dir().'/pw-search-static-'.uniqid();
        $filesystem = new Filesystem();
        $filesystem->mkdir($dir);

        try {
            $dispatcher->dispatch(new StaticPostGenerateEvent($app, $dir, false, []));

            self::assertFileExists($dir.'/search.json');
            self::assertFileExists($dir.'/search/loupe.db');

            $json = json_decode((string) file_get_contents($dir.'/search.json'), true);
            self::assertIsArray($json);
        } finally {
            $filesystem->remove($dir);
        }
    }
}
