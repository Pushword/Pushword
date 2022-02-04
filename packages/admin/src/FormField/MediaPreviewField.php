<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<MediaInterface>
 */
final class MediaPreviewField extends AbstractField
{
    /**
     * @var array<string, mixed>
     */
    private ?array $relatedPages = null;

    /**
     * @param FormMapper<MediaInterface> $form
     *
     * @return FormMapper<MediaInterface>
     */
    public function formField(FormMapper $form): FormMapper
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

        return $form;
    }

    private function issetRelatedPages(): bool
    {
        $relatedPages = $this->getRelatedPages();

        return [] !== $relatedPages || $relatedPages['mainImage']->count() > 0; // @phpstan-ignore-line
    }

    /**
     * @return mixed[]|array<string, mixed>
     */
    private function getRelatedPages(): array
    {
        if (null !== $this->relatedPages) {
            return $this->relatedPages;
        }

        $media = $this->admin->getSubject();

        $this->relatedPages = Repository::getPageRepository($this->admin->getEntityManager(), $this->admin->getPageClass())
            ->getPagesUsingMedia($media);

        return $this->relatedPages;
    }

    private function showRelatedPages(): string
    {
        return $this->admin->getTwig()->render(
            '@pwAdmin/media/media_show.relatedPages.html.twig',
            ['related_pages' => $this->getRelatedPages()]
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
