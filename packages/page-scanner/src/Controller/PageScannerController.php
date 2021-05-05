<?php

namespace Pushword\PageScanner\Controller;

use DateInterval;
use Exception;
use Pushword\Core\Utils\LastTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

/**
 * @IsGranted("ROLE_PUSHWORD_ADMIN")
 */
final class PageScannerController extends AbstractController
{
    private Filesystem $filesystem;

    private string $pageScanInterval;

    private static $fileCache;

    public function __construct(
        Filesystem $filesystem,
        string $varDir,
        string $pageScanInterval
    ) {
        $this->filesystem = $filesystem;
        $this->pageScanInterval = $pageScanInterval;
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

    public function scanAction($force = 0): Response
    {
        if ($this->filesystem->exists(self::$fileCache)) {
            $errors = unserialize(file_get_contents(self::$fileCache));
            $lastEdit = filemtime(self::$fileCache);
        } else {
            $lastEdit = 0;
            $errors = [];
        }

        $lastTime = new LastTime(self::$fileCache);
        if ($force || false === $lastTime->wasRunSince(new DateInterval($this->pageScanInterval))) {
            exec('cd ../ && php bin/console pushword:page-scanner:scan > /dev/null 2>/dev/null &');
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
