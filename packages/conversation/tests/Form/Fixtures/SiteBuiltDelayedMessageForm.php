<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Form\Fixtures;

use Override;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\AbstractConversationForm;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/** A site form built on FormType rather than initForm(), with a delayed notification. */
final class SiteBuiltDelayedMessageForm extends AbstractConversationForm
{
    /** @return FormBuilderInterface<Message> */
    #[Override]
    protected function getStepOne(): FormBuilderInterface
    {
        $action = $this->initForm()->getAction();
        $this->message->setCustomProperty('notificationDelay', 600);

        return $this->formFactory->createBuilder(FormType::class, $this->message, ['csrf_protection' => false])
            ->setAction($action)
            ->add('authorEmail', EmailType::class, ['constraints' => $this->getAuthorEmailConstraints()])
            ->add('content', TextareaType::class);
    }
}
