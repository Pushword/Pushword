<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Twig\Attribute\AsTwigFunction;

final class ContentExtension
{
    /** @var array<int|string, SplitContent> */
    private array $cache = [];

    public function __construct(
        private readonly ContentPipelineFactory $pipelineFactory,
    ) {
    }

    #[AsTwigFunction('mainContentSplit', isSafe: ['html'])]
    public function mainContentSplit(Page $page): SplitContent
    {
        $id = $page->id ?? 'obj_'.spl_object_id($page);

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $processedContent = $this->pipelineFactory->get($page)->getMainContent();

        $this->cache[$id] = new SplitContent($processedContent, $page);

        return $this->cache[$id];
    }
}
