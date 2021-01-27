<?php

namespace Pushword\AdminBlockEditor;

use Pushword\Admin\FormField\AbstractField;
use Sonata\AdminBundle\Form\FormMapper;
use Tbmatuka\EditorjsBundle\Form\EditorjsType;

class PageMainContentFormField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('jsMainContent', EditorjsType::class, [
            'required' => false,
            'label' => ' ',
            'help_html' => true,
            'help' => 'admin.page.mainContent.help',
            'mapped' => false,
            'data' => $this->admin->getSubject()->jsMainContent,
        ]);
    }
}
