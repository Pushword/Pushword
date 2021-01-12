<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class OgTwitterSiteField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper->add('twitterSite', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterSite.label',
            'help_html' => true,
            'help' => 'admin.page.twitterSite.help',
        ]);
    }
}
