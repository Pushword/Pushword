<?php

namespace Pushword\PageScanner\Controller;

use Pushword\Core\Utils\LastTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class PageScannerController extends AbstractController
{
    private static ?string $fileCache = null;

    public function __construct(
        private readonly Filesystem $filesystem,
        string $varDir,
        private readonly string $pageScanInterval
    ) {
        self::setFileCache($varDir);
    }

    public static function setFileCache(string $varDir): void
    {
        self::$fileCache = null !== self::$fileCache && '' !== self::$fileCache ? self::$fileCache : $varDir.'/page-scan';
    }

    public static function fileCache(): string
    {
        if (! \is_string(self::$fileCache)) {
            throw new \Exception('setFileCache($varDir) must be setted before call fileCache()');
        }

        return self::$fileCache;
    }

    public function scanAction(int $force = 0): Response
    {
        $force = (bool) $force;

        if (null === self::$fileCache) {
            throw new \LogicException();
        }

        if ($this->filesystem->exists(self::$fileCache)) {
            $errors = unserialize(\Safe\file_get_contents(self::$fileCache));
            $lastEdit = \Safe\filemtime(self::$fileCache);
        } else {
            $lastEdit = 0;
            $errors = [];
        }

        $lastTime = new LastTime(self::$fileCache);
        if ($force || ! $lastTime->wasRunSince(new \DateInterval($this->pageScanInterval))) {
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
