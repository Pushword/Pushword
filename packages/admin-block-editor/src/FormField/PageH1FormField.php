<?php

namespace Pushword\AdminBlockEditor\FormField;

use Pushword\Admin\FormField\PageH1Field;
use Sonata\AdminBundle\Form\FormMapper;

class PageH1FormField extends PageH1Field
{
    public function formField(
        FormMapper $formMapper,
        string $style = ''
    ): FormMapper {
        return parent::formField($formMapper, $style);
    }
}
