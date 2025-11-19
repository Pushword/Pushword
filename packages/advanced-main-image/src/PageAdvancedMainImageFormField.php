<?php

namespace Pushword\AdvancedMainImage;

use Override;
use Pushword\Admin\FormField\PageMainImageField;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Twig\Attribute\AsTwigFunction;

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
            'choices' => MainImageFormatsConfig::getFormatsStatic(),
            'data' => (int) $subject->getCustomPropertyScalar('mainImageFormat'),
        ]);
    }

    #[AsTwigFunction('heroSize')]
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
