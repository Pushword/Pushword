<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postPersist'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postUpdate'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postRemove'])]
final readonly class MediaCacheInvalidationListener
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private ?CacheItemPoolInterface $cache,
        private EntityManagerInterface $em,
        private RenderEpoch $renderEpoch,
    ) {
    }

    public function postPersist(): void
    {
        // No epoch bump: a media that did not exist cannot be referenced by
        // already-generated HTML, and uploads are the high-frequency media write.
        $this->invalidate();
    }

    public function postUpdate(): void
    {
        $this->invalidate();
        $this->renderEpoch->bump();
    }

    public function postRemove(): void
    {
        $this->invalidate();
        $this->renderEpoch->bump();
    }

    private function invalidate(): void
    {
        if (null !== $this->cache) {
            $item = $this->cache->getItem(MediaRepository::VERSION_CACHE_KEY);
            $value = $item->isHit() ? $item->get() : null;
            $item->set((\is_int($value) ? $value : 0) + 1);
            $this->cache->save($item);
        }

        $this->em->getRepository(Media::class)->resetFileNameIndexLight();
    }
}
