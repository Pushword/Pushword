<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Type\DateTimePickerType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

/**
 * @extends AbstractField<PageInterface>
 */
class PagePublishedAtField extends AbstractField
{
    /**
     * @param FormMapper<PageInterface> $form
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('publishedAt', DateTimePickerType::class, [
            'format' => DateTimeType::HTML5_FORMAT,
            'datepicker_options' => [
                'useCurrent' => true,
            ],
            'label' => $this->admin->getMessagePrefix().'.publishedAt.label',
            'help' => $this->getHelp(),
            'help_html' => true,
        ]);
    }

    private function getHelp(): string
    {
        $published = $this->getSubject()->getPublishedAt() <= new \DateTime('now');

        // TODO: translate
        return null !== $this->getSubject()->getId() ?
            $this->trans($this->admin->getMessagePrefix().'.publishedAt.'.($published ? 'online' : 'draft'))
            : '';
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
