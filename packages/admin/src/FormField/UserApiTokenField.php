<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field as EasyField;
use Pushword\Admin\Form\Type\ApiTokenType;
use Pushword\Core\Entity\User;

/**
 * @extends AbstractField<User>
 */
class UserApiTokenField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        $user = $this->admin->getSubject();
        $hasToken = null !== $user->apiToken && '' !== $user->apiToken;

        return EasyField::new('apiToken', 'adminUserApiTokenLabel')
            ->onlyOnForms()
            ->setFormType(ApiTokenType::class)
            ->setFormTypeOptions([
                'required' => false,
                'attr' => [
                    'readonly' => true,
                    'class' => 'form-control font-monospace',
                    'data-has-token' => $hasToken ? '1' : '0',
                ],
            ])
            ->setHelp('adminUserApiTokenHelp');
    }
}
