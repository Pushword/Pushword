<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;

use function Safe\json_encode;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @template T of Page|Media
 *
 * @extends AbstractField<Page|Media>
 */
class TagsField extends AbstractField
{
    /**
     * @return string[]
     */
    private function getAllTags(): array
    {
        $subject = $this->admin->getSubject();

        if ($subject instanceof Page) {
            /** @var PageRepository */
            $pageRepo = $this->admin->getEntityManager()->getRepository(Page::class); // @phpstan-ignore-line

            $host = $this->admin->getRequest()->query->getString('host', $subject->getHost());

            return $pageRepo->getAllTags($host);
        }

        // assert($subject instanceof Media);

        /** @var MediaRepository */
        $mediaRepo = $this->admin->getEntityManager()->getRepository(Media::class); // @phpstan-ignore-line

        return $mediaRepo->getAllTags();
    }

    public function formField(FormMapper $form): void
    {
        $allTags = $this->getAllTags();
        $subject = $this->admin->getSubject();
        $isMediaAdmin = $subject instanceof Media;

        $form->add('tags', TextType::class, [
            'required' => false,
            'attr' => [
                'class' => 'textarea-no-newline tagsField'
                  .($isMediaAdmin ? ' tagsFieldMedia' : ''),
                'placeholder' => 'admin.page.tags.label',
                'data-tags' => json_encode($allTags),
                // 'data-search-results-hook' => 'suggestSearchHookForPageTags', // data-search-results-hook=suggestSearchHookForPageTags
                'autofocus' => '',
            ],
            'row_attr' => [
                'class' => 'tagsFieldWrapper '
                  .($isMediaAdmin ? ' tagsFieldWrapperMedia' : ' ce-block__content'),
            ],
            'label' => $isMediaAdmin ? 'Tags' : ' ',
            'help' => ' <div class="textSuggester" style="display:none;"></div>'
                .'<script>setTimeout(function () {
                    const element = document.querySelector("'.(null === $subject->getId() ? '[data-tags]' : '[id$=_h1] textarea').'");
                    element.focus();
                    element.selectionStart = element.selectionEnd = element.value.length;
                }, 500)</script>',
            'help_html' => true,
        ]);
    }// .($page->getId() === null ?
}
