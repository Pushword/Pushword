<?php

namespace Pushword\Conversation\Form;

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class MultiStepMessageForm extends MessageForm
{
    protected function getStepOne(): FormBuilderInterface
    {
        $form = $this->initForm();

        $form->add('content', TextareaType::class);

        return $form;
    }

    protected function getStepTwo(): FormBuilderInterface
    {
        $form = $this->initForm();

        $form->add('authorEmail', EmailType::class, ['constraints' => $this->getAuthorEmailConstraints()]);
        $form->add('authorName', null, ['constraints' => $this->getAuthorNameConstraints()]);

        return $form;
    }
}
