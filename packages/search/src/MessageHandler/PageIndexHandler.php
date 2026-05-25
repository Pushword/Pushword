<?php

namespace Pushword\Search\MessageHandler;

use Pushword\Core\Repository\PageRepository;
use Pushword\Search\Message\ReindexPageMessage;
use Pushword\Search\Message\RemovePageMessage;
use Pushword\Search\Service\Indexer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class PageIndexHandler
{
    public function __construct(
        private PageRepository $pageRepository,
        private Indexer $indexer,
    ) {
    }

    #[AsMessageHandler]
    public function reindex(ReindexPageMessage $message): void
    {
        $page = $this->pageRepository->find($message->pageId);
        if (null === $page) {
            return;
        }

        $this->indexer->indexPage($page);
    }

    #[AsMessageHandler]
    public function remove(RemovePageMessage $message): void
    {
        $this->indexer->removePage($message->pageId, $message->host);
    }
}
