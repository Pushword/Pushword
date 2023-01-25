<?php

namespace Pushword\AdminBlockEditor\FormField;

use Pushword\Admin\FormField\AbstractField;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<PageInterface>
 */
class PageMainContentFormField extends AbstractField
{
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('mainContent', EditorjsType::class, [
            'required' => false,
            'label' => ' ',
            'help_html' => true,
            'help' => 'admin.page.mainContent.help',
            'mapped' => false,
            'data' => $this->admin->getSubject()->getMainContent(),
            'attr' => ['page_id' => $this->admin->getSubject()->getId()],
        ]);
    }
}
