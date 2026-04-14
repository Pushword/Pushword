<?php

namespace Pushword\Conversation\FormField;

use Override;
use Pushword\Admin\FormField\TagsField;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Repository\MessageRepository;

/**
 * @template T of Message
 *
 * @extends TagsField<Message>
 */
class ConversationTagsField extends TagsField
{
    /**
     * @return string[]
     */
    #[Override]
    protected function getAllTags(): array
    {
        $messageRepo = $this->entityManager()->getRepository(Message::class);
        assert($messageRepo instanceof MessageRepository); // @phpstan-ignore-line

        return $messageRepo->getAllTags();
    }
}
