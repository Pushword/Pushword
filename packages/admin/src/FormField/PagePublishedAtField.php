<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;

/**
 * @extends AbstractField<PageInterface>
 */
class PagePublishedAtField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('publishedAt', DateTimePickerType::class, [
            'format' => CreatedAtField::DateTimePickerFormat,
            'datepicker_options' => CreatedAtField::DateTimePickerOptions,
            'label' => $this->formFieldManager->getMessagePrefix().'.publishedAt.label',
            'help' => $this->getHelp(),
            'help_html' => true,
            'attr' => ['data-selector' => 'publishedAtToDraft'],
        ]);
    }

    private function getHelp(): string
    {
        return $this->formFieldManager->twig->render('@pwAdmin/page/page_draft.html.twig', ['page' => $this->getSubject(), 'draft' => $this->getSubject()->getPublishedAt() > new \DateTime('now')]);
    }

    private function getSubject(): PageInterface
    {
        return $this->admin->getSubject();
    }

    private function trans(string $id): string
    {
        return $this->admin->getTranslator()->trans($id);
    }
}
