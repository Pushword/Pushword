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
        return ChoiceField::new('locale', 'admin.user.locale.label')
            ->onlyOnForms()
            ->setChoices($this->getLocaleChoices())
            ->renderAsNativeWidget()
            ->setFormTypeOption('required', true)
            ->setHelp('admin.user.locale.help');
    }

    /**
     * @return array<string, string>
     */
    private function getLocaleChoices(): array
    {
        $choices = [];
        foreach ($this->formFieldManager->apps->getApps() as $app) {
            foreach ($app->getLocales() as $locale) {
                $choices[$locale] = $locale;
            }
        }

        ksort($choices);

        return $choices;
    }
}
