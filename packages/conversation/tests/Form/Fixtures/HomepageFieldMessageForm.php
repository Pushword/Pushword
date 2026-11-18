<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Form\Fixtures;

use Override;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\Extension\SpamProtectionExtension;
use Pushword\Conversation\Form\MessageForm;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

/** A site form asking visitors for a field that has the honeypot's name. */
final class HomepageFieldMessageForm extends MessageForm
{
    /** @return FormBuilderInterface<Message> */
    #[Override]
    protected function getStepOne(): FormBuilderInterface
    {
        return parent::getStepOne()->add(SpamProtectionExtension::HONEYPOT, UrlType::class, [
            'mapped' => false,
            'required' => false,
            'default_protocol' => null,
        ]);
    }
}
