<?php

namespace Pushword\Core\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
//use WebPConvert\Convert\Converters\Stack as WebPConverter;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Pushword\Core\Entity\MediaInterface;
use Spatie\Async\Pool;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaCacheGenerator
{
    protected $projectDir;
    protected $pool;
    protected $cacheManager;
    protected $filterManager;
    protected $dataManager;
    protected $em;

    protected static $webPConverterOptions = [
        'converters' => ['cwebp'],
        //'try-cwebp'  => false,
        //'converters' => ['cwebp', 'gd', 'vips', 'imagick', 'gmagick', 'imagemagick', 'graphicsmagick', 'wpc', 'ewww'],
    ];

    public function __construct(
        string $projectDir,
        CacheManager $cacheManager,
        DataManager $dataManager,
        FilterManager $filterManager,
        EntityManagerInterface $em
    ) {
        $this->projectDir = $projectDir;
        $this->cacheManager = $cacheManager;
        $this->dataManager = $dataManager;
        $this->filterManager = $filterManager;
        $this->em = $em;
    }

    public function generateCache(MediaInterface $media)
    {
        $this->updatePaletteColor($media);

        $this->pool = Pool::create();

        $this->createWebP($media); // do i need it ?! Yes, if the generation failed, liip will use this one

        $path = '/'.$media->getRelativeDir().'/'.$media->getMedia();
        $binary = $this->getBinary($path);
        //$pathWebP = '/'.$media->getRelativeDir().'/'.$media->getSlug().'.webp';

        $filters = array_keys($this->filterManager->getFilterConfiguration()->all());

        foreach ($filters as $filter) {
            $this->storeImageInCache($path, $binary, $filter);
            $this->imgToWebP($media, $filter);
            //$this->storeImageInCache($pathWebP, $binary, $filter); liip not optimized...
        }
        $this->pool->wait();

        $this->em->flush();
    }

    protected function updatePaletteColor(MediaInterface $media)
    {
        $img = $this->projectDir.$media->getPath();
        $palette = Palette::fromFilename($img, Color::fromHexToInt('#FFFFFF'));
        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract();
        $media->setMainColor(Color::fromIntToHex($colors[0]));
    }

    public function getBinary($path)
    {
        try {
            $binary = $this->dataManager->find('default', $path);
        } catch (NotLoadableException $e) {
            throw new NotFoundHttpException('Source image could not be found', $e);
        }

        return $binary;
    }

    public function storeImageInCache($path, $binary, $filter): void
    {
        try {
            $this->cacheManager->store(
                $this->filterManager->applyFilter($binary, $filter),
                $path,
                $filter
            );
        } catch (\RuntimeException $e) {
            $msg = 'Unable to create image for path "%s" and filter "%s". '.'Message was "%s"';

            throw new \RuntimeException(sprintf($msg, $path, $filter, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Todo: test the win to generate liip in multithread.
     */
    public static function storeImageInCacheStatic($path, $binary, $filter, $cacheManager, $filterManager): void
    {
        try {
            $cacheManager->store(
                $filterManager->applyFilter($binary, $filter),
                $path,
                $filter
            );
        } catch (\RuntimeException $e) {
            $msg = 'Unable to create image for path "%s" and filter "%s". '.'Message was "%s"';

            throw new \RuntimeException(sprintf($msg, $path, $filter, $e->getMessage()), 0, $e);
        }
    }

    public static function imgToWebPStatic($path, $webPPath, $webPConverterOptions, string $filter): void
    {
        $webPConverter = new WebPConverter(
            $path,
            $webPPath,
            $webPConverterOptions
        );

        try {
            $webPConverter->doConvert();
        } catch (\Exception $e) {
            $msg = 'Unable to create image for path "%s" and filter "%s". '.'Message was "%s"';

            throw new \RuntimeException(sprintf($msg, $path, $filter, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Use the liip generated filter to generate the webp equivalent.
     */
    public function imgToWebP(MediaInterface $media, string $filter): void
    {
        $pathJpg = $this->projectDir.'/public/'.$media->getRelativeDir().'/'.$filter.'/'.$media->getMedia();
        $pathWebP = $this->projectDir.'/public/'.$media->getRelativeDir().'/'.$filter.'/'.$media->getSlug().'.webp';
        $webPConverterOptions = self::$webPConverterOptions;

        $this->pool->add(function () use ($pathJpg, $pathWebP, $webPConverterOptions, $filter) {
            // took 46s (vs 43s) to add liip generation in async
            //exec($projectDir.'/bin/console liip:imagine:cache:resolve "'.$path.'" --force --filter='.$filter
            //.' >/dev/null 2>&1 &');
            self::imgToWebPStatic($pathJpg, $pathWebP, $webPConverterOptions, $filter);
        });
    }

    public function createWebP(MediaInterface $media): void
    {
        $destination = $this->projectDir.'/'.$media->getRelativeDir().'/'.$media->getSlug().'.webp';
        $source = $this->projectDir.$media->getPath();
        //self::createWebPStatic($destination, $source);

        $this->pool->add(function () use ($destination, $source) {
            self::createWebPStatic($destination, $source);
        });
    }

    public static function createWebPStatic($destination, $source): void
    {
        $webPConverter = new WebPConverter($source, $destination, self::$webPConverterOptions);

        try {
            $webPConverter->doConvert();
        } catch (\Exception $e) {
            $msg = 'Unable to create image for path "%s" and filter "%s". '.'Message was "%s"';

            throw new \RuntimeException(sprintf($msg, $source, 'importing from img', $e->getMessage()), 0, $e);
        }
    }
}
