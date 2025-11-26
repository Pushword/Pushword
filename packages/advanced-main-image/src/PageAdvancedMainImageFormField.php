<?php

namespace Pushword\AdvancedMainImage;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Override;
use Pushword\Admin\FormField\PageMainImageField;
use Pushword\AdvancedMainImage\DependencyInjection\Configuration as AdvancedMainImageConfiguration;
use Pushword\Core\Entity\Page;
use Twig\Attribute\AsTwigFunction;

class PageAdvancedMainImageFormField extends PageMainImageField
{
    #[Override]
    public function getEasyAdminField(): FieldInterface|iterable|null
    {
        $fields = [];
        $baseField = parent::getEasyAdminField();

        if ($baseField instanceof FieldInterface) {
            $fields[] = $baseField;
        } elseif (is_iterable($baseField)) {
            foreach ($baseField as $field) {
                $fields[] = $field;
            }
        }

        /** @var Page $subject */
        $subject = $this->admin->getSubject();
        $subject->registerCustomPropertyField('mainImageFormat');

        $fields[] = ChoiceField::new('mainImageFormat', 'admin.page.mainImageFormat.label')
            ->onlyOnForms()
            ->setChoices($this->resolveMainImageFormats($subject))
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('data', (int) $subject->getCustomPropertyScalar('mainImageFormat'));

        return $fields;
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

        /** @var array<string, int> $formats */
        $formats = $app->getArray('main_image_formats', AdvancedMainImageConfiguration::DEFAULT_MAIN_IMAGE_FORMATS);

        return $formats;
    }
}
