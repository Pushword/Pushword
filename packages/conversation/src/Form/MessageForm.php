<?php

namespace Pushword\Conversation\Form;

use Pushword\Core\Entity\UserInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class MessageForm implements ConversationFormInterface
{
    use FormTrait;

    protected function getStepOne(): FormBuilderInterface
    {
        /*
        if ($this->getUser()) {
            $this->message->setAuthorEmail($this->getUser()->getEmail());
            $this->message->setAuthorName($this->getUser()->getUsername());
            $user = true;
        }
        /**/

        $formBuilder = $this->initForm();

        if (null === $this->getUser()) { // ! isset($user) ||
            $formBuilder->add('authorEmail', EmailType::class, ['constraints' => $this->getAuthorEmailConstraints()]);
            $formBuilder->add('authorName', null, ['constraints' => $this->getAuthorNameConstraints()]);
        }

        $formBuilder->add('content', TextareaType::class);

        return $formBuilder;
    }

    protected function getUser(): ?UserInterface
    {
        if (null === $token = $this->security->getToken()) {
            return null;
        }

        if (! \is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return null;
        }

        if (! $user instanceof UserInterface) {
            throw new \LogicException();
        }

        return $user;
    }
}
