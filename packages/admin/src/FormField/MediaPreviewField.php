<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<Media>
 */
final class MediaPreviewField extends AbstractField
{
    /**
     * @var ?Page[]
     */
    private ?array $relatedPages = null;

    /**
     * @param FormMapper<Media> $form
     */
    public function formField(FormMapper $form): void
    {
        if ('' !== $this->admin->getSubject()->getMedia()) {
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
     * @return Page[]
     */
    private function getRelatedPages(): array
    {
        if (null !== $this->relatedPages) {
            return $this->relatedPages;
        }

        $media = $this->admin->getSubject();

        /** @var PageRepository $pageRepo */
        $pageRepo = $this->formFieldManager->em->getRepository(Page::class); // @phpstan-ignore-line
        $this->relatedPages = $pageRepo->getPagesUsingMedia($media);

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
