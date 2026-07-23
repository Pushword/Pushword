<?php

namespace Pushword\Core\Twig;

use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

final class ContentExtension implements ResetInterface
{
    /** @var array<int|string, SplitContent> */
    private array $cache = [];

    public function __construct(
        private readonly ContentPipelineFactory $pipelineFactory,
        #[Autowire(service: 'cache.pushword_markdown')]
        private readonly ?CacheItemPoolInterface $tocCache = null,
    ) {
    }

    /**
     * Worker-mode safety (kernel.reset): this memo is keyed by page id and
     * short-circuits before the content-hash cache.pushword_markdown pool, so that
     * pool's self-invalidation cannot save us. Left alive across requests, a page
     * rendered once would serve that same body for its id until the worker restarts,
     * however the content changed (api PUT, admin save, pw:flat:sync).
     */
    public function reset(): void
    {
        $this->cache = [];
    }

    #[AsTwigFunction('mainContentSplit', isSafe: ['html'])]
    public function mainContentSplit(Page $page): SplitContent
    {
        $id = $page->id ?? 'obj_'.spl_object_id($page);

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $processedContent = $this->pipelineFactory->get($page)->getMainContent();

        $this->cache[$id] = new SplitContent($processedContent, $page, $this->tocCache);

        return $this->cache[$id];
    }
}
