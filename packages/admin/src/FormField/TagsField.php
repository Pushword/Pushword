<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\SharedTrait\Taggable;

use function Safe\json_encode;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

/**
 * @template T of Taggable
 *
 * @extends AbstractField<Taggable>
 */
class TagsField extends AbstractField
{
    /**
     * @return string[]
     */
    protected function getAllTags(): array
    {
        $subject = $this->admin->getSubject();

        if ($subject instanceof Page) {
            $host = $this->currentRequest()->query->getString('host', $subject->getHost());

            return $this->pageRepo()->getAllTags($host);
        }

        // assert($subject instanceof Media);

        return $this->mediaRepo()->getAllTags();
    }

    public function getEasyAdminField(): ?FieldInterface
    {
        $allTags = $this->getAllTags();
        $subject = $this->admin->getSubject();
        $isPageAdmin = $subject instanceof Page;

        return $this->buildEasyAdminField('tagsVirtual', TextType::class, [
            'required' => false,
            'getter' => static function (?object $viewData, FormInterface $form): string {
                if (! \is_object($viewData) || ! method_exists($viewData, 'getTags')) {
                    return '';
                }

                $value = $viewData->getTags();

                return \is_string($value) ? $value : '';
            },
            'setter' => static function (?object &$viewData, mixed $submittedValue, FormInterface $form): void {
                if (! \is_object($viewData) || ! method_exists($viewData, 'setTags')) {
                    return;
                }

                $viewData->setTags($submittedValue);
            },
            'attr' => [
                'class' => 'textarea-no-newline tagsField'
                    .($isPageAdmin ? '' : ' tagsFieldMedia'),
                'placeholder' => 'admin.page.tags.label',
                'data-tags' => json_encode($allTags),
                'autofocus' => '',
            ],
            'row_attr' => [
                'class' => 'tagsFieldWrapper '
                    .($isPageAdmin ? ' ce-block__content' : ' tagsFieldWrapperMedia'),
            ],
            'label' => $isPageAdmin ? ' ' : 'Tags',
            'help' => ' <div class="textSuggester" style="display:none;"></div>'
                .'<script>setTimeout(function () {
                    const element = document.querySelector("'.(null === $subject->getId() ? '[data-tags]' : '[id$=_h1]').'");
                    element.focus();
                    element.selectionStart = element.selectionEnd = element.value.length;
                }, 500)</script>',
            'help_html' => true,
        ]);
    }
}
