<?php

namespace Pushword\Conversation\Form;

use Pushword\Conversation\Entity\Message;
use Symfony\Component\Form\FormBuilderInterface;

class NewsletterForm extends AbstractConversationForm implements ConversationFormInterface
{
    protected function getStepOne(): FormBuilderInterface
    {
        $form = $this->initForm();
        $form->add('authorEmail', options: ['constraints' => $this->getAuthorEmailConstraints(true)]);

        $this->message->setContent($this->translator->trans('conversationSuscribeToNewsletter'));

        return $form;
    }

    /** @return FormBuilderInterface<Message> */
    protected function getStepTwo(): FormBuilderInterface
    {
        $form = $this->initForm();

        $form->add('authorName', null, ['constraints' => $this->getAuthorNameConstraints()]);

        return $form;
    }
}
