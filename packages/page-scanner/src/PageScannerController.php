<?php

namespace Pushword\PageScanner;

use DateInterval;
use Exception;
use Pushword\Core\Utils\LastTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @IsGranted("ROLE_PUSHWORD_ADMIN")
 */
class PageScannerController extends AbstractController
{
    /**
     * @var PageScannerService
     */
    protected $scanner;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var ContainerBag
     */
    protected $params;
    protected $filesystem;
    protected $eventDispatcher;

    protected static $fileCache;

    public function __construct(
        Filesystem $filesystem,
        string $varDir
    ) {
        $this->filesystem = $filesystem;
        $this->setFileCache($varDir);
    }

    public static function setFileCache(string $varDir): void
    {
        self::$fileCache = self::$fileCache ?: $varDir.'/page-scan';
    }

    public static function fileCache(): string
    {
        if (! \is_string(self::$fileCache)) {
            throw new Exception('setFileCache($varDir) must be setted before call fileCache()');
        }

        return self::$fileCache;
    }

    public function scanAction()
    {
        if ($this->filesystem->exists(self::$fileCache)) {
            $errors = unserialize(file_get_contents(self::$fileCache));
            $lastEdit = filemtime(self::$fileCache);
        } else {
            $lastEdit = 0;
            $errors = [];
        }

        $lastTime = new LastTime(self::$fileCache);
        if (false === $lastTime->wasRunSince(new DateInterval('PT5M'))) { // todo config
            exec('cd ../ && php bin/console pushword:page:scan > /dev/null 2>/dev/null &');
            $newRunLaunched = true;
            $lastTime->setWasRun('now', false);
        }

        return $this->render('@pwPageScanner/results.html.twig', [
            'newRun' => $newRunLaunched ?? false,
            'lastEdit' => $lastEdit,
            'errorsByPages' => $errors,
        ]);
    }
}
