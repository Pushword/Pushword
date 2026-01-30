<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postPersist'])]
final readonly class MediaDuplicateDetector
{
    use MediaAlertTrait;

    public function __construct(
        private MediaRepository $mediaRepo,
        private RouterInterface $router,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    public function postPersist(Media $media): void
    {
        $duplicate = $this->mediaRepo->findDuplicate($media);
        if (null === $duplicate) {
            return;
        }

        $this->alert('warning', 'mediaDuplicateWarning', [
            '%deleteMediaUrl%' => $this->router->generate('admin_media_delete', ['entityId' => $media->id]),
            '%sameMediaEditUrl%' => $this->router->generate('admin_media_edit', ['entityId' => $duplicate->id]),
            '%name%' => $duplicate->getAlt(),
        ]);
    }
}
