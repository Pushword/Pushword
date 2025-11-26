<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class OgTwitterSiteField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('twitterSite', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterSite.label',
            'help_html' => true,
            'help' => 'admin.page.twitterSite.help',
        ]);
    }
}
