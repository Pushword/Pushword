<?php

namespace Pushword\AdvancedMainImage;

use Override;
use Pushword\Admin\FormField\PageMainImageField;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class PageAdvancedMainImageFormField extends PageMainImageField
{
    #[Override]
    public function formField(FormMapper $form): void
    {
        parent::formField($form);

        $subject = $this->admin->getSubject();

        $form->add('mainImageFormat', ChoiceType::class, [
            'required' => false,
            'mapped' => false,
            'label' => 'admin.page.mainImageFormat.label',
            'choices' => [
                'admin.page.mainImageFormat.none' => 1,
                'admin.page.mainImageFormat.normal' => 0,
                'admin.page.mainImageFormat.13fullscreen' => 2,
                'admin.page.mainImageFormat.34fullscreen' => 3,
                // 'admin.page.mainImageFormat.fullscreen' => 4,
            ],
            'data' => \intval($subject->getCustomPropertyScalar('mainImageFormat')),
        ]);
    }

    public static function formatToRatio(int $format): string
    {
        return match ($format) {
            2 => '[33vh]',
            3 => '[75vh]',
            4 => 'screen',
            default => '[75vh]',
        };
    }
}
