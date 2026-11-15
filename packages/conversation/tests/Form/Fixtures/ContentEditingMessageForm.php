<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Form\Fixtures;

use Override;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\MultiStepMessageForm;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class ContentEditingMessageForm extends MultiStepMessageForm
{
    /** @return FormBuilderInterface<Message> */
    #[Override]
    protected function getStepTwo(): FormBuilderInterface
    {
        return parent::getStepTwo()->add('content', TextareaType::class);
    }
}
