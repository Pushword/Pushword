<?php

namespace Pushword\StaticGenerator\Cache\MessageHandler;

use Pushword\Core\Repository\PageRepository;
use Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage;
use Pushword\StaticGenerator\Cache\PageCacheFileManager;
use Pushword\StaticGenerator\Cache\PageCacheGeneratorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PageCacheRefreshHandler
{
    public function __construct(
        private PageRepository $pageRepository,
        private PageCacheGeneratorInterface $generator,
        private PageCacheFileManager $fileManager,
    ) {
    }

    public function __invoke(PageCacheRefreshMessage $message): void
    {
        $page = $this->pageRepository->find($message->pageId);
        if (null === $page) {
            return;
        }

        if (! $this->fileManager->isCacheable($page)) {
            $this->fileManager->delete($page);

            return;
        }

        $this->generator->generatePage($page->host, $page->getRealSlug());
    }
}
