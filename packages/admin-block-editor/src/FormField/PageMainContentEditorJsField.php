<?php

namespace Pushword\AdminBlockEditor\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\FormField\AbstractField;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Pushword\Core\Entity\Page;

/**
 * @extends AbstractField<Page>
 */
class PageMainContentEditorJsField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        $page = $this->admin->getSubject();

        return $this->buildEasyAdminField('mainContent', EditorjsType::class, [
            'required' => false,
            'label' => ' ',
            'help_html' => true,
            'help' => 'adminPageMainContentHelp',
            'attr' => ['page_id' => $page->getId(), 'page_host' => $page->getHost()],
        ]);
    }
}
