<?php

declare(strict_types=1);

use App\Kernel;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__, 5).'/vendor/autoload.php';

if (! isset($argv[1], $argv[2])) {
    throw new RuntimeException('Expected a media ID and its new filename.');
}

new Dotenv()->loadEnv(__DIR__.'/../../.env');
$kernel = new Kernel('test', true);
$kernel->boot();
$container = new KernelBrowser($kernel)->getContainer();
$dispatcher = new class implements BackgroundTaskDispatcherInterface {
    /** @var list<string> */
    public array $queued = [];

    public function dispatch(string $processType, array $commandParts, string $commandPattern): void
    {
        // A stopped worker accepts jobs without generating any derivatives.
        $this->queued[] = $commandPattern;
    }
};
$container->set(BackgroundTaskDispatcherInterface::class, $dispatcher);
$em = $container->get('doctrine')->getManager();
$media = $em->find(Media::class, (int) $argv[1]);
if (! $media instanceof Media) {
    throw new RuntimeException('The media to rename must exist.');
}

$media->setFileName($argv[2]);
$em->flush();
echo json_encode(['fileName' => $media->getFileName(), 'queued' => $dispatcher->queued], \JSON_THROW_ON_ERROR);
$kernel->shutdown();
