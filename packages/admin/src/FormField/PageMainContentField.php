<?php

declare(strict_types=1);

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\Controller\PageRedirectionCrudController;
use Pushword\Admin\Form\Type\RedirectionTargetType;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class PageMainContentField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        if ($this->admin instanceof PageRedirectionCrudController) {
            return $this->buildEasyAdminField('mainContent', RedirectionTargetType::class, [
                'label' => 'adminPageRedirectionTo',
                'help' => 'adminPageRedirectionTargetHelp',
                'required' => true,
            ]);
        }

        return $this->buildEasyAdminField('mainContent', TextareaType::class, [
            'attr' => [
                'style' => 'min-height: 50vh;font-size:125%; max-width:900px',
                'data-editor' => 'markdown',
                'data-gutter' => 0,
            ],
            'required' => false,
            'label' => false,
            'help_html' => true,
            'help' => 'adminPageMainContentHelp',
        ]);
    }
}
