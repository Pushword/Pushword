<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class PageMainImageField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        $formMapper->add('mainImage', \Sonata\AdminBundle\Form\Type\ModelListType::class, [
            'required' => false,
            'class' => $this->admin->getMediaClass(),
            'label' => ' ', //'admin.page.mainImage.label',
            'btn_edit' => false,
        ]);

        $formMapper->add('mainImageFormat', ChoiceType::class, [
            'required' => false,
            'label' => 'admin.page.mainImageFormat.label',
            'choices' => [
                'admin.page.mainImageFormat.none' => 0,
                'admin.page.mainImageFormat.normal' => 1,
                'admin.page.mainImageFormat.13fullscreen' => 2,
                'admin.page.mainImageFormat.34fullscreen' => 3,
                //'admin.page.mainImageFormat.fullscreen' => 4,
            ],
        ]);

        return $formMapper;
    }

    public static function formatToRatio($format): string
    {
        switch ($format) {
            case 2: return 'screen-1/3';
            case 3: return 'screen-3/4';
            case 4: return 'screen';
        }

        return 'screen-3/4';
    }
}
