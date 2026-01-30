<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Pushword\Core\Entity\User;

/**
 * @extends AbstractField<User>
 */
class UserLocaleField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return ChoiceField::new('locale', 'adminUserLocaleLabel')
            ->onlyOnForms()
            ->setChoices($this->getLocaleChoices())
            ->renderAsNativeWidget()
            ->setFormTypeOption('required', true)
            ->setHelp('adminUserLocaleHelp');
    }

    /**
     * @return array<string, string>
     */
    private function getLocaleChoices(): array
    {
        $choices = [];
        foreach ($this->formFieldManager->apps->getAll() as $app) {
            foreach ($app->getLocales() as $locale) {
                $choices[$locale] = $locale;
            }
        }

        ksort($choices);

        return $choices;
    }
}
