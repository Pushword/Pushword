<?php

namespace Pushword\Conversation\Form;

use LogicException;
use Pushword\Core\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class MessageForm extends AbstractConversationForm implements ConversationFormInterface
{
    protected function getStepOne(): FormBuilderInterface
    {
        $formBuilder = $this->initForm();

        if (null === $this->getUser()) { // ! isset($user) ||
            $formBuilder->add('authorEmail', EmailType::class, ['constraints' => $this->getAuthorEmailConstraints()]);
            $formBuilder->add('authorName', null, ['constraints' => $this->getAuthorNameConstraints()]);
        }

        $formBuilder->add('content', TextareaType::class);

        return $formBuilder;
    }

    protected function getUser(): ?User
    {
        if (null === $token = $this->security->getToken()) {
            return null;
        }

        if (! \is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return null;
        }

        if (! $user instanceof User) {
            throw new LogicException();
        }

        return $user;
    }
}
