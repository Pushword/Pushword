<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Form\FormMapper;

final class MediaPreviewField extends AbstractField
{
    /** @var array|null */
    private $relatedPages;

    public function formField(FormMapper $formMapper): FormMapper
    {
        if ($this->admin->getSubject()->getMedia()) {
            $formMapper->with('admin.media.preview.label', [
                'class' => 'col-md-12',
                'description' => $this->showMediaPreview(),
                'empty_message' => false,
            ])->end();

            if ($this->issetRelatedPages()) {
                $formMapper->with('admin.media.related.label', [
                    'class' => 'col-md-12',
                    'description' => $this->showRelatedPages(),
                    'empty_message' => false,
                ])->end();
            }
        }

        return $formMapper;
    }

    private function issetRelatedPages(): bool
    {
        $relatedPages = $this->getRelatedPages();

        if (! empty($relatedPages['content']) || $relatedPages['mainImage']->count() > 0) {
            return true;
        } else {
            return false;
        }
    }

    private function getRelatedPages(): ?array
    {
        if (null !== $this->relatedPages) {
            return $this->relatedPages;
        }

        $media = $this->admin->getSubject();

        $pages = Repository::getPageRepository($this->admin->getEntityManager(), $this->admin->getPageClass())
            ->getPagesUsingMedia($media->getMedia()); //$this->imageManager->getBrowserPath($media));

        $this->relatedPages = [
            'content' => $pages,
            'mainImage' => $media->getMainImagePages(),
        ];

        return $this->relatedPages;
    }

    private function showRelatedPages(): string
    {
        return $this->admin->getTwig()->render(
            '@pwAdmin/media/media_show.relatedPages.html.twig',
            $this->getRelatedPages()
        );
    }

    private function showMediaPreview(): string
    {
        $media = $this->admin->getSubject();

        $template = $this->admin->getImageManager()->isImage($media) ?
            '@pwAdmin/media/media_show.preview_image.html.twig'
            : '@pwAdmin/media/media_show.preview.html.twig';

        return $this->admin->getTwig()->render(
            $template,
            [
                'media' => $media,
            ]
        );
    }
}
