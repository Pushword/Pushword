<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Twig\Attribute\AsTwigFunction;

final class ContentExtension
{
    /** @var array<int|string, SplitContent> */
    private array $cache = [];

    public function __construct(
        private readonly ManagerPool $managerPool,
    ) {
    }

    #[AsTwigFunction('mainContentSplit', isSafe: ['html'])]
    public function mainContentSplit(Page $page): SplitContent
    {
        $id = $page->id ?? 'obj_'.spl_object_id($page);

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        // Get processed main content (with markdown, links, etc. applied)
        $manager = $this->managerPool->getManager($page);
        /** @var string $processedContent */
        $processedContent = $manager->getMainContent();

        $this->cache[$id] = new SplitContent($processedContent, $page);

        return $this->cache[$id];
    }
}
