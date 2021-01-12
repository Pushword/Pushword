<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PageMainContentField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('mainContent', TextareaType::class, [
            'attr' => [
                'style' => 'min-height: 50vh;font-size:125%; max-width:900px',
                'data-editor' => 'markdown',
                'data-gutter' => 0,
            ],
            'required' => false,
            'label' => ' ',
            'help_html' => true,
            'help' => 'admin.page.mainContent.help',
        ]);
    }

    /* TODO : keep it to integrate editorJs
    protected function configureFormFieldMainContentContentType(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('mainContentType', ChoiceType::class, [
            'choices' => [
                'admin.page.mainContentType.choice.defaultAppValue' => '0',
                'admin.page.mainContentType.choice.raw' => '1',
                'admin.page.mainContentType.choice.editorjs' => '2',
            ],
            'label' => 'admin.page.mainContentType.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.markdown.help',
        ]);
    }*/
}
