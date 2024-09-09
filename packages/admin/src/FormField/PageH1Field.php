<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class PageH1Field extends AbstractField
{
    /**
     * @var string
     */
    private const DEFAULT_STYLE = 'font-size: 22px !important; font-weight: 700; border:0; color:#111827;'
        .'padding: 10px 10px 0px 10px; margin-top:-23px; margin-bottom:-23px;
        max-width: 640px; ';

    /**
     * @param FormMapper<Page> $form
     */
    public function formField(FormMapper $form, string $style = ''): void
    {
        $style = '' !== $style ? $style : self::DEFAULT_STYLE;

        // Todo move style to view
        $form->add('h1', TextareaType::class, [
            'required' => false,
            'attr' => [
                'class' => 'autosize textarea-no-newline ce-block__content',
                'placeholder' => 'admin.page.title.label',
                'style' => $style,
            ],
            'label' => ' ',
        ]);
    }
}
