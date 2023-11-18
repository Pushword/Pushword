<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<UserInterface>
 */
class UserEmailField extends AbstractField
{
    /**
     * @param FormMapper<UserInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form
                ->add('email', null, ['label' => 'admin.user.email.label']);
    }
}
