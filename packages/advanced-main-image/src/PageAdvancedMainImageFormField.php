<?php

namespace Pushword\AdvancedMainImage;

use Override;
use Pushword\Admin\FormField\PageMainImageField;
use Pushword\AdvancedMainImage\DependencyInjection\Configuration as AdvancedMainImageConfiguration;
use Pushword\Core\Entity\Page;
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
            'choices' => $this->resolveMainImageFormats($subject),
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

    /**
     * @return array<string, int>
     */
    private function resolveMainImageFormats(?Page $page): array
    {
        $host = null !== $page ? $page->getHost() : null;
        $app = $this->formFieldManager->apps->get($host);
        $formats = $app->getArray('main_image_formats', AdvancedMainImageConfiguration::DEFAULT_MAIN_IMAGE_FORMATS);

        return [] === $formats ? AdvancedMainImageConfiguration::DEFAULT_MAIN_IMAGE_FORMATS : $formats;
    }
}
