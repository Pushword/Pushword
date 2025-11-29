<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class PageTitleField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('title', TextareaType::class, [
            'label' => 'adminPageTitleLabel',
            'required' => false,
            'help_html' => true,
            'help' => 'adminPageTitleHelp',
            'attr' => ['class' => 'titleToMeasure autosize textarea-no-newline'],
        ]);
    }
}
