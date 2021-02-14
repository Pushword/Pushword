<?php

namespace Pushword\AdminBlockEditor\FormField;

use Pushword\Admin\FormField\AbstractField;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Sonata\AdminBundle\Form\FormMapper;

class PageMainContentFormField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('mainContent', EditorjsType::class, [
            'required' => false,
            'label' => ' ',
            'help_html' => true,
            'help' => 'admin.page.mainContent.help',
            'mapped' => false,
            'data' => $this->admin->getSubject()->jsMainContent,
            'attr' => ['page_id' => $this->admin->getSubject()->getId()],
        ]);
    }
}
