<?php

namespace Pushword\Core\EventListener;

use LogicException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaConflictResolver;

use function Safe\preg_replace;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Event\Event;

#[AutoconfigureTag('kernel.event_listener', ['event' => 'vich_uploader.pre_upload'])]
final readonly class MediaNamingListener
{
    use MediaAlertTrait;

    public function __construct(
        private string $projectDir,
        private MediaConflictResolver $conflictResolver,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    public function onVichUploaderPreUpload(Event $event): void
    {
        $media = $this->getMediaFromEvent($event);
        $media->resetHash();
        $media->setHash();

        $propertyMapping = $event->getMapping();

        $absoluteDir = $propertyMapping->getUploadDestination().'/'.($propertyMapping->getUploadDir($media) ?? '');
        $media->setProjectDir($this->projectDir)->setStoreIn($absoluteDir);

        $this->setNameIfEmpty($media);

        if ($this->conflictResolver->resolveConflicts($media)) {
            $this->alert('success', 'mediaNameWasChanged');
        }

        if ('' === $media->getFileName()) {
            $media->setFileName($media->getMediaFromFilename($media->getSlug()));
        }
    }

    private function getMediaFromEvent(Event $event): Media
    {
        $media = $event->getObject();
        if (! $media instanceof Media) {
            throw new LogicException();
        }

        return $media;
    }

    private function setNameIfEmpty(Media $media): void
    {
        if ('' !== $media->getAlt(true)) {
            return;
        }

        if ('' !== $media->getSlug()) {
            $media->setAlt($media->getSlug());
        }

        /** @var string */
        $name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $media->getMediaFileName());
        $media->setAlt($name);
    }
}
