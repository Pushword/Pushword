<?php

namespace Pushword\Conversation\Flat;

use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\FlatFileContentDirFinder;
use Symfony\Contracts\Service\Attribute\Required;

trait ConversationContextTrait
{
    private AppPool $apps;

    private FlatFileContentDirFinder $contentDirFinder;

    private MessageRepository $messageRepository;

    #[Required]
    public function initConversationContext(
        AppPool $apps,
        FlatFileContentDirFinder $contentDirFinder,
        MessageRepository $messageRepository,
    ): void {
        $this->apps = $apps;
        $this->contentDirFinder = $contentDirFinder;
        $this->messageRepository = $messageRepository;
    }

    private function resolveApp(?string $host): AppConfig
    {
        return null !== $host
            ? $this->apps->switchCurrentApp($host)->get()
            : $this->apps->get();
    }

    private function isGlobalMode(): bool
    {
        return (bool) $this->apps->get()->get('flat_conversation_global');
    }

    private function buildCsvPath(?AppConfig $app = null): string
    {
        if ($this->isGlobalMode()) {
            return $this->contentDirFinder->getBaseDir().'/conversation.csv';
        }

        $app ??= $this->apps->get();

        return $this->contentDirFinder->get($app->getMainHost()).'/conversation.csv';
    }

    private function getMessageRepository(): MessageRepository
    {
        return $this->messageRepository;
    }

    /**
     * @param array<string, string|null> $row
     */
    public function resolveMessageClass(?string $type, array $row = []): ?string
    {
        if (in_array((int) ($row['rating'] ?? 0), [1, 2, 3, 4, 5], true)) {
            return Review::class;
        }

        $type = trim($type ?? '');

        if (Review::class === $type || 'review' === strtolower($type) || str_contains($type, 'Review')) {
            return Review::class;
        }

        return Message::class;
    }
}
