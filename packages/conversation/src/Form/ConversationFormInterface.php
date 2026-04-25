<?php

namespace Pushword\Conversation\Form;

use Pushword\Conversation\Entity\Message;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

interface ConversationFormInterface
{
    /**
     * @return FormBuilderInterface<Message>
     */
    public function getCurrentStep(): FormBuilderInterface;

    /**
     * @param FormInterface<Message|null> $form
     */
    public function validCurrentStep(FormInterface $form): string;

    public function getShowFormTemplate(): string;

    /**
     * @param FormInterface<Message|null> $form
     */
    public function showForm(FormInterface $form): string;
}
