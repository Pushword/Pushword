<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<MediaInterface>
 */
final class MediaPreviewField extends AbstractField
{
    /**
     * @var ?PageInterface[]
     */
    private ?array $relatedPages = null;

    /**
     * @param FormMapper<MediaInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        if (null !== $this->admin->getSubject()->getMedia()) {
            $form->with('admin.media.preview.label', [
                'class' => 'col-md-12',
                'description' => $this->showMediaPreview(),
                'empty_message' => false,
            ])->end();

            if ($this->issetRelatedPages()) {
                $form->with('admin.media.related.label', [
                    'class' => 'col-md-12',
                    'description' => $this->showRelatedPages(),
                    'empty_message' => false,
                ])->end();
            }
        }
    }

    private function issetRelatedPages(): bool
    {
        $relatedPages = $this->getRelatedPages();

        return [] !== $relatedPages;
    }

    /**
     * @return PageInterface[]
     */
    private function getRelatedPages(): array
    {
        if (null !== $this->relatedPages) {
            return $this->relatedPages;
        }

        $media = $this->admin->getSubject();

        $this->relatedPages = Repository::getPageRepository($this->formFieldManager->em, $this->formFieldManager->pageClass)
            ->getPagesUsingMedia($media);

        return $this->relatedPages;
    }

    private function showRelatedPages(): string
    {
        return $this->formFieldManager->twig->render(
            '@pwAdmin/media/media_show.relatedPages.html.twig',
            ['related_pages' => $this->getRelatedPages()]
        );
    }

    private function showMediaPreview(): string
    {
        $media = $this->admin->getSubject();

        $template = $this->formFieldManager->imageManager->isImage($media) ?
            '@pwAdmin/media/media_show.preview_image.html.twig'
            : '@pwAdmin/media/media_show.preview.html.twig';

        return $this->formFieldManager->twig->render(
            $template,
            [
                'media' => $media,
            ]
        );
    }
}
