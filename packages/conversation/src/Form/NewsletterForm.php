<?php

namespace Pushword\Conversation\Form;

use Symfony\Component\Form\FormBuilderInterface;

class NewsletterForm extends AbstractConversationForm implements ConversationFormInterface
{
    protected function getStepOne(): FormBuilderInterface
    {
        $form = $this->initForm();
        $form->add('authorEmail', options: $this->getAuthorEmailConstraints(true));

        $this->message->setContent($this->translator->trans('conversation.suscribeToNewsletter'));

        return $form;
    }

    protected function getStepTwo(): FormBuilderInterface
    {
        $form = $this->initForm();

        $form->add('authorName', null, ['constraints' => $this->getAuthorNameConstraints()]);

        return $form;
    }
}
