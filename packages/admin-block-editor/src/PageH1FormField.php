<?php

namespace Pushword\AdminBlockEditor;

use Pushword\Admin\FormField\PageH1Field;
use Sonata\AdminBundle\Form\FormMapper;

class PageH1FormField extends PageH1Field
{
    public function formField(
        FormMapper $formMapper,
        string $style = 'font-weight: 700; padding: 10px 10px 0px 10px;margin-top:-23px; margin-bottom:-23px'
    ): FormMapper {
        return parent::formField($formMapper, $style);
    }
}
