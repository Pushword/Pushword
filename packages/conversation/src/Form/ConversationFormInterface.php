<?php

namespace Pushword\Conversation\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

interface ConversationFormInterface
{
    public function getCurrentStep(): FormBuilderInterface;

    public function validCurrentStep(FormInterface $form): string;

    public function getShowFormTemplate(): string;

    public function showForm(FormInterface $form): string;
}
