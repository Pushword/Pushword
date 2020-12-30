<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Service\MediaCacheGenerator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelEvents;
use Vich\UploaderBundle\Event\Event;

class MediaListener
{
    protected $projectDir;
    protected $iterate = 1;
    protected $em;
    protected $eventDispatcher;
    protected $filesystem;
    protected $projetDir;
    protected $cacheManager;
    protected $cacheGenerator;

    public function __construct(
        string $projectDir,
        string $projetDir,
        EntityManagerInterface $em,
        CacheManager $cacheManager,
        EventDispatcherInterface $eventDispatcher,
        FileSystem $filesystem,
        MediaCacheGenerator $cacheGenerator
    ) {
        $this->projectDir = $projectDir;
        $this->em = $em;
        $this->cacheManager = $cacheManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->filesystem = $filesystem;
        $this->projetDir = $projetDir;
        $this->cacheGenerator = $cacheGenerator;
    }

    /**
     * Check if name exist.
     */
    public function onVichUploaderPreUpload(Event $event)
    {
        $media = $event->getObject();

        $this->checkIfThereIsAName($media);
        $this->checkIfNameEverExistInDatabase($media);
    }

    /**
     * renameMediaOnMediaNameUpdate.
     */
    public function preUpdate(MediaInterface $media, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('media')) {
            //var_dump($media->getRelativeDir().'/'.$media->getMediaBeforeUpdate()); exit;
            $this->filesystem->rename(
                $this->projetDir.'/'.$media->getRelativeDir().'/'.$media->getMediaBeforeUpdate(),
                $this->projetDir.'/'.$media->getRelativeDir().'/'.$media->getMedia()
            );
            $this->cacheManager->remove('/'.$media->getRelativeDir().'/'.$media->getMediaBeforeUpdate());
        }
    }

    public function preRemove(MediaInterface $media)
    {
        $this->filesystem->remove($this->projetDir.'/'.$media->getRelativeDir().'/'.$media->getMedia());
        $this->cacheManager->remove('/'.$media->getRelativeDir().'/'.$media->getMediaBeforeUpdate());
    }

    /**
     * Si l'utilisateur ne propose pas de nom pour l'image,
     * on récupère celui d'origine duquel on enlève son extension.
     */
    protected function checkIfThereIsAName($media)
    {
        if (null === $media->getName() || empty($media->getName())) {
            $media->setName(preg_replace('/\\.[^.\\s]{3,4}$/', '', $media->getMediaFile()->getClientOriginalName()));
        }
    }

    protected function checkIfNameEverExistInDatabase($media)
    {
        $same = $this->em->getRepository(\get_class($media))->findOneBy(['name' => $media->getName()]);
        if ($same && (null == $media->getId() || $media->getId() != $same->getId())) {
            $media->setName(preg_replace('/\([0-9]+\)$/', '', $media->getName()).' ('.$this->iterate.')');
            ++$this->iterate;
            $this->checkIfNameEverExistInDatabase($media);
        }
    }

    /**
     * Update RelativeDir.
     */
    public function onVichUploaderPostUpload(Event $event)
    {
        $media = $event->getObject();
        $mapping = $event->getMapping();

        $absoluteDir = $mapping->getUploadDestination().'/'.$mapping->getUploadDir($media);
        $relativeDir = substr_replace($absoluteDir, '', 0, \strlen($this->projectDir) + 1);

        $media->setRelativeDir($relativeDir);

        if (false !== strpos($media->getMimeType(), 'image/')) {
            $this->updatePaletteColor($media);

            $this->cacheManager->remove($media->getFullPath());

            // Quick hack to have correct URI in image previewer
            // A better way would be to
            // implement https://github.com/liip/LiipImagineBundle/issues/242#issuecomment-71647135
            $path = '/'.$media->getRelativeDir().'/'.$media->getMedia();
            $this->cacheGenerator->storeImageInCache($path, $this->cacheGenerator->getBinary($path), 'default');

            $this->eventDispatcher->addListener(
                KernelEvents::TERMINATE,
                function () use ($media) {
                    $this->cacheGenerator->generateCache($media);
                }
            );
        }
    }

    protected function updatePaletteColor(MediaInterface $media)
    {
        $img = $this->projectDir.$media->getPath();
        $palette = Palette::fromFilename($img, Color::fromHexToInt('#FFFFFF'));
        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract();
        $media->setMainColor(Color::fromIntToHex($colors[0]));
    }
}
