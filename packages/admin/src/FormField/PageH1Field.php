<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<PageInterface>
 */
class PageH1Field extends AbstractField
{
    /**
     * @var string
     */
    final public const DEFAULT_STYLE = 'font-size: 22px !important; border:0;'
            .'font-weight: 700; padding: 10px 10px 0px 10px; margin-top:-23px; margin-bottom:-23px max-width: 640px; color:#111827';

    /**
     * @param FormMapper<PageInterface> $form
     *
     * @return FormMapper<PageInterface>
     */
    public function formField(FormMapper $form, string $style = ''): FormMapper
    {
        $style = '' !== $style ? $style : self::DEFAULT_STYLE;

        // Todo move style to view
        return $form->add('h1', TextareaType::class, [
            'required' => false,
            'attr' => ['class' => 'autosize textarea-no-newline ce-block__content', 'placeholder' => 'admin.page.title.label', 'style' => $style],
            'label' => ' ',
        ]);
    }
}
