<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;

/**
 * @extends AbstractField<Page>
 */
class PageCustomCanonicalField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('customCanonical', null, [
            'label' => 'adminPageCustomCanonicalLabel',
            'required' => false,
        ]);
    }
}
