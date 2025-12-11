<?php

namespace Pushword\Core\Service;

use Cocur\Slugify\Slugify;
use Exception;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Image;
use Intervention\Image\ImageManager as InteventionImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\ImageOptimizer\OptimizerChainFactory;
use Pushword\Core\Utils\MediaRenamer;

use function Safe\file_put_contents;
use function Safe\filesize;

use Spatie\ImageOptimizer\OptimizerChain;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class ImageManager
{
    private readonly OptimizerChain $optimizer;

    private ?ImageInterface $lastThumb = null;

    private readonly InteventionImageManager $interventionManager;

    private string $resolvedDriver;

    private ?string $avifencPath = null;

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    public function __construct(
        private array $filterSets,
        private readonly string $publicDir,
        private readonly string $projectDir,
        private readonly string $publicMediaDir,
        private readonly string $mediaDir,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly string $imageDriver = 'auto',
    ) {
        $this->optimizer = OptimizerChainFactory::create();
        $this->renamer = new MediaRenamer();
        $this->interventionManager = $this->createInterventionManager();
        $this->avifencPath = $this->findAvifenc();
    }

    private function createInterventionManager(): InteventionImageManager
    {
        $driver = $this->imageDriver;

        if ('auto' === $driver) {
            // Prefer Imagick if available (better encoders)
            $driver = \extension_loaded('imagick') ? 'imagick' : 'gd';
        }

        $this->resolvedDriver = $driver;

        return 'imagick' === $driver
            ? new InteventionImageManager(new ImagickDriver())
            : new InteventionImageManager(new GdDriver());
    }

    private function findAvifenc(): ?string
    {
        // Fall back to system avifenc
        $finder = new ExecutableFinder();

        return $finder->find('avifenc');
    }

    public function getResolvedDriver(): string
    {
        return $this->resolvedDriver;
    }

    public function hasAvifenc(): bool
    {
        return null !== $this->avifencPath;
    }

    /**
     * @param array<string, array<string, mixed>> $filters
     */
    public function setFilters(array $filters): void
    {
        $this->filterSets = $filters;
    }

    public function isImage(Media $media): bool
    {
        return $media->isImage();
    }

    public function generateCache(Media $media): void
    {
        $image = $this->getImage($media);

        $this->updateMainColor($media, $image);

        $media->setDimensions([$image->width(), $image->height()]);

        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            $lastImg = $this->generateFilteredCache($media, $filterName, $image);
            if ('thumb' === $filterName) {
                $this->lastThumb = $lastImg;
            }
        }

        $this->runBackgroundOptimization($media->getFileName());
    }

    /**
     * Generate only a quick preview for admin by copying original file.
     * No resizing - pw:image:cache will handle proper sizing in background.
     *
     * @return ImageInterface|null Returns null if image format is not supported by current driver
     */
    public function generateQuickThumb(Media $media): ?ImageInterface
    {
        // Just copy original file for instant preview (no resizing, no format conversion)
        $this->copyOriginalToFilter($media, 'md');

        // Try to read dimensions and main color
        try {
            $image = $this->getImage($media);
            $this->updateMainColor($media, $image);
            $media->setDimensions([$image->width(), $image->height()]);

            return $image;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Copy original media file to filter directory (for formats that can't be re-encoded).
     */
    private function copyOriginalToFilter(Media $media, string $filterName): void
    {
        $sourcePath = $this->mediaStorage->getLocalPath($media->getFileName());
        $destPath = $this->getFilterPath($media, $filterName);

        $this->createFilterDir(\dirname($destPath));

        if (file_exists($sourcePath)) {
            copy($sourcePath, $destPath);
        }
    }

    /**
     * Run full cache generation in background (including slow AVIF encoding).
     */
    public function runBackgroundCacheGeneration(string $fileName): void
    {
        $process = new Process([
            'php',
            'bin/console',
            'pw:image:cache',
            $fileName,
        ]);
        $process->setWorkingDirectory($this->projectDir);
        $process->disableOutput();
        $process->start();
    }

    private function updateMainColor(Media $media, ?ImageInterface $image = null): void
    {
        if (! $image instanceof Image) {
            return;
        }

        $imageForPalette = clone $image;
        $color = $imageForPalette->pickColor(0, 0)->toHex('#'); // ->reduceColors(1)
        // previously doing this $color = $imageForPalette->limitColors(1)->pickColor(0, 0, 'hex');

        $media->setMainColor($color);
    }

    public function getLastThumb(): ?ImageInterface
    {
        return $this->lastThumb;
    }

    /**
     * @param array<string, mixed>|string $filter
     */
    public function generateFilteredCache(
        Media|string $media,
        array|string $filter,
        ?ImageInterface $originalImage = null
    ): ImageInterface {
        if (\is_array($filter)) {
            $filterName = array_keys($filter)[0];
            $filters = $filter;
        } else {
            $filters = $this->filterSets;
            $filterName = $filter;
        }

        $image = null === $originalImage ? $this->getImage($media)
            : ('default' === $filterName ? $originalImage : clone $originalImage); // don't clone if default for speed perf

        foreach ($filters[$filterName]['filters'] as $filter => $parameters) { // @phpstan-ignore-line
            $parameters = \is_array($parameters) ? $parameters : [$parameters];
            \call_user_func_array([$image, $filter], $parameters); // @phpstan-ignore-line
        }

        $quality = (int) ($filters[$filterName]['quality'] ?? 90); // @phpstan-ignore-line
        /** @var string[] $formats */
        $formats = $filters[$filterName]['formats'] ?? ['original', 'webp']; // @phpstan-ignore-line BC default

        $this->createFilterDir(\dirname($this->getFilterPath($media, $filterName)));

        // Detect source format (AVIF/WebP need special handling - AutoEncoder corrupts them)
        $sourceIsAvif = $this->isSourceAvif($media);
        $sourceIsWebp = $this->isSourceWebp($media);

        if (\in_array('original', $formats, true)) {
            $outputPath = $this->getFilterPath($media, $filterName);
            if ($sourceIsAvif) {
                // For AVIF sources, use avifenc if available, otherwise copy source
                $this->encodeAvif($image, $outputPath, $quality);
            } elseif ($sourceIsWebp) {
                // For WebP sources, use WebP encoder
                $image->toWebp($quality)->save($outputPath);
            } else {
                // For other formats, AutoEncoder works fine
                $image->encode(new AutoEncoder(quality: $quality))->save($outputPath);
            }
        }

        if (\in_array('avif', $formats, true)) {
            $this->encodeAvif($image, $this->getFilterPath($media, $filterName, 'avif'), $quality);
        }

        if (\in_array('webp', $formats, true)) {
            $image->toWebp($quality)->save($this->getFilterPath($media, $filterName, 'webp'));
        }

        return $image;
    }

    private function isSourceAvif(Media|string $media): bool
    {
        if ($media instanceof Media) {
            return 'image/avif' === $media->getMimeType();
        }

        // Check file extension for string path
        $ext = strtolower(pathinfo($media, \PATHINFO_EXTENSION));

        return 'avif' === $ext;
    }

    private function isSourceWebp(Media|string $media): bool
    {
        if ($media instanceof Media) {
            return 'image/webp' === $media->getMimeType();
        }

        // Check file extension for string path
        $ext = strtolower(pathinfo($media, \PATHINFO_EXTENSION));

        return 'webp' === $ext;
    }

    private function createFilterDir(string $path): void
    {
        if (! file_exists($path)) {
            (new Filesystem())->mkdir($path);
        }
    }

    /**
     * Encode image to AVIF format.
     * Priority: avifenc binary > imagick/gd library.
     */
    private function encodeAvif(ImageInterface $image, string $outputPath, int $quality): void
    {
        // Try avifenc binary first (best quality/size ratio)
        if (null !== $this->avifencPath) {
            // Save a temporary PNG for avifenc input
            $tempPath = sys_get_temp_dir().'/'.uniqid('avif_', true).'.png';
            $image->toPng()->save($tempPath);

            // Convert quality (0-100) to avifenc min/max (0-63, lower is better)
            // quality 100 -> min=0, max=10
            // quality 80 -> min=20, max=30
            // quality 50 -> min=32, max=42
            $minQ = (int) ((100 - $quality) * 0.63);
            $maxQ = min(63, $minQ + 10);

            $process = new Process([
                $this->avifencPath,
                '--min', (string) $minQ,
                '--max', (string) $maxQ,
                '--speed', '4',  // Balance between speed and compression
                $tempPath,
                $outputPath,
            ]);
            $process->setTimeout(120);
            $process->run();

            @unlink($tempPath);

            if ($process->isSuccessful() && file_exists($outputPath)) {
                return;
            }

            // If avifenc failed, fall through to library encoding
        }

        // Fall back to library encoding (imagick or gd)
        $image->toAvif($quality)->save($outputPath);
    }

    public function optimize(Media $media): void
    {
        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            $this->optimizeFiltered($media, $filterName);
        }
    }

    private function optimizeFiltered(Media $media, string $filterName): void
    {
        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['original', 'webp'];

        // Check if required files exist based on formats
        $needsGeneration = false;
        if (\in_array('original', $formats, true) && ! file_exists($this->getFilterPath($media, $filterName))) {
            $needsGeneration = true;
        }

        if (\in_array('avif', $formats, true) && ! file_exists($this->getFilterPath($media, $filterName, 'avif'))) {
            $needsGeneration = true;
        }

        if (\in_array('webp', $formats, true) && ! file_exists($this->getFilterPath($media, $filterName, 'webp'))) {
            $needsGeneration = true;
        }

        if ($needsGeneration) {
            $this->generateFilteredCache($media, $filterName);
        }

        // Optimize each format that exists
        if (\in_array('original', $formats, true) && file_exists($this->getFilterPath($media, $filterName))) {
            $this->optimizer->optimize($this->getFilterPath($media, $filterName));
        }

        if (\in_array('webp', $formats, true) && file_exists($this->getFilterPath($media, $filterName, 'webp'))) {
            $this->optimizer->optimize($this->getFilterPath($media, $filterName, 'webp'));
        }

        // Note: AVIF optimization not supported by Spatie ImageOptimizer
    }

    public function getFilterPath(Media|string $media, string $filterName, ?string $extension = null, bool $browserPath = false): string
    {
        $media = $media instanceof Media ? $media->getFileName() : Filepath::filename($media);

        $fileName = null === $extension ? $media : Filepath::removeExtension($media).'.'.$extension;

        return ($browserPath ? '' : $this->publicDir).'/'.$this->publicMediaDir.'/'.$filterName.'/'.$fileName;
    }

    #[AsTwigFilter('image')]
    public function getBrowserPath(
        Media|string $media,
        string $filterName = 'default',
        ?string $extension = null,
        bool $checkFileExists = false,
    ): string {
        // If extension is explicitly provided, use it
        if (null !== $extension) {
            return $this->getFilterPath($media, $filterName, $extension, true);
        }

        // Otherwise, return the first available format that exists: avif > webp > original
        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['original', 'webp'];

        // Try avif first if configured
        if (\in_array('avif', $formats, true)) {
            $avifPath = $this->getFilterPath($media, $filterName, 'avif');
            if (! $checkFileExists || file_exists($avifPath)) {
                return $this->getFilterPath($media, $filterName, 'avif', true);
            }
        }

        // Try webp if configured
        if (\in_array('webp', $formats, true)) {
            $webpPath = $this->getFilterPath($media, $filterName, 'webp');
            if (! $checkFileExists || file_exists($webpPath)) {
                return $this->getFilterPath($media, $filterName, 'webp', true);
            }
        }

        // Try original if configured
        if (\in_array('original', $formats, true)) {
            $originalPath = $this->getFilterPath($media, $filterName);
            if (! $checkFileExists || file_exists($originalPath)) {
                return $this->getFilterPath($media, $filterName, null, true);
            }
        }

        // Fallback: return original path even if file doesn't exist (background processing may be pending)
        return $this->getFilterPath($media, $filterName, null, true);
    }

    /**
     * @return int[] index 0 contains width, index 1 height
     */
    #[AsTwigFunction('image_dimensions')]
    public function getDimensions(Media|string $media): array
    {
        $path = $this->getFilterPath($media, 'xs');

        $size = @getimagesize($path);
        if (false === $size) {
            throw new Exception('`'.$path.'` not found');
        }

        return [$size[0], $size[1]];
    }

    /**
     * Returns the preferred modern image format for a given filter.
     * Priority: avif > webp > null (no modern format).
     */
    #[AsTwigFunction('preferred_modern_format')]
    public function getPreferredModernFormat(string $filterName = 'xs'): ?string
    {
        /** @var string[] $formats */
        $formats = $this->filterSets[$filterName]['formats'] ?? ['original', 'webp'];

        if (\in_array('avif', $formats, true)) {
            return 'avif';
        }

        if (\in_array('webp', $formats, true)) {
            return 'webp';
        }

        return null;
    }

    /**
     * @param Media|string $media string must be the accessible path (absolute) to the image file
     */
    private function getImage(Media|string $media): ImageInterface
    {
        $path = $media instanceof Media
            ? $this->mediaStorage->getLocalPath($media->getFileName())
            : $media;

        try {
            return $this->interventionManager->read($path);
        } catch (Exception) {
            throw new Exception($this->resolvedDriver.' cannot read image `'.$path.'`');
        }
    }

    public function remove(Media|string $media): void
    {
        $mediaFileName = $media instanceof Media ? $media->getFileName() : Filepath::filename($media);
        $mediaBase = Filepath::removeExtension($mediaFileName);

        $filterNames = array_keys($this->filterSets);
        foreach ($filterNames as $filterName) {
            // Remove original format
            $path = $this->publicDir.'/'.$this->publicMediaDir.'/'.$filterName.'/'.$mediaFileName;
            if (file_exists($path)) {
                @unlink($path);
            }

            // Remove AVIF variant
            $avifPath = $this->publicDir.'/'.$this->publicMediaDir.'/'.$filterName.'/'.$mediaBase.'.avif';
            if (file_exists($avifPath)) {
                @unlink($avifPath);
            }

            // Remove WebP variant
            $webpPath = $this->publicDir.'/'.$this->publicMediaDir.'/'.$filterName.'/'.$mediaBase.'.webp';
            if (file_exists($webpPath)) {
                @unlink($webpPath);
            }
        }
    }

    // ImageImport
    private readonly MediaRenamer $renamer;

    private function generateFileName(string $url, string $mimeType, string $slug, bool $hashInFilename): string
    {
        $slug = (new Slugify())->slugify($slug);

        return ('' !== $slug ? $slug : pathinfo($url, \PATHINFO_BASENAME))
            .($hashInFilename ? '-'.substr(md5(sha1($url)), 0, 4) : '')
            .'.'.str_replace(['image/', 'jpeg'], ['', 'jpg'], $mimeType);
    }

    public function importExternal(
        string $image,
        string $name = '',
        string $slug = '',
        bool $hashInFilename = true
        // , $ifNameIsTaken = null
    ): Media {
        $imageLocalImport = $this->cacheExternalImage($image);

        if (false === $imageLocalImport || ($imgSize = getimagesize($imageLocalImport)) === false) {
            throw new Exception('Image `'.$image.'` was not imported.');
        }

        $fileName = $this->generateFileName($image, $imgSize['mime'], '' !== $slug ? $slug : $name, $hashInFilename);

        $media = new Media();
        $media
            ->setProjectDir($this->projectDir)
                ->setStoreIn($this->mediaDir)
                ->setMimeType($imgSize['mime'])
                ->setSize(filesize($imageLocalImport))
                ->setDimensions([$imgSize[0], $imgSize[1]])
                ->setFileName($fileName)
                ->setSlug(Filepath::removeExtension($fileName))
                ->setAlt(str_replace(["\n", '"'], ' ', $name));

        $this->finishImportExternalByCopyingLocally($media, $imageLocalImport);
        $this->renamer->reset();

        return $media;
    }

    private function finishImportExternalByCopyingLocally(Media $media, string $imageLocalImport): void
    {
        if ($this->mediaStorage->fileExists($media->getFileName())) {
            $existingLocalPath = $this->mediaStorage->getLocalPath($media->getFileName());
            if (sha1_file($existingLocalPath) !== sha1_file($imageLocalImport)) {
                // an image exist with same name/slug but is a different file
                $this->renamer->rename($media);
                $this->finishImportExternalByCopyingLocally($media, $imageLocalImport);

                return;
            }

            return; // same image already exists
        }

        // Upload file to storage
        $stream = fopen($imageLocalImport, 'r');
        if (false !== $stream) {
            $this->mediaStorage->writeStream($media->getFileName(), $stream);
            fclose($stream);
        }

        $this->generateCache($media);
    }

    /**
     * @noRector
     */
    public function cacheExternalImage(string $src): false|string
    {
        $filePath = sys_get_temp_dir().'/'.sha1($src);
        if (file_exists($filePath)) {
            return $filePath;
        }

        if (! is_readable($src) && \function_exists('curl_init')) {
            $curl = curl_init($src);
            curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
            /** @var false|string $content */
            $content = curl_exec($curl);
            unset($curl);
        } else {
            $content = file_get_contents($src);
        }

        if (false === $content) {
            return false;
        }

        if (false === imagecreatefromstring($content)) {
            return false;
        }

        file_put_contents($filePath, $content);

        return $filePath;
    }

    private function runBackgroundOptimization(string $fileName): void
    {
        // before
        // exec('cd ../ && php bin/console pw:image:optimize '.
        //  $media->getFileName().' > /dev/null 2>/dev/null &');
        $process = new Process([
            'php',
            'bin/console',
            'pw:image:optimize',
            $fileName,
        ]);
        $process->setWorkingDirectory($this->projectDir);
        $process->disableOutput();
        $process->start();
    }
}
