<?php

namespace Pushword\Core\Content;

use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Attribute\AsTwigFunction;

final class ContentPipelineFactory
{
    /** @var array<(string|int), ContentPipeline> */
    private array $pipelines = [];

    public function __construct(
        public readonly SiteRegistry $apps,
        public readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterRegistry $filterRegistry,
        private readonly ManagerPool $legacyManagerPool,
    ) {
    }

    public function get(Page $page): ContentPipeline
    {
        $id = $page->id ?? 'obj_'.spl_object_id($page);

        return $this->pipelines[$id] ??= new ContentPipeline(
            $this,
            $this->eventDispatcher,
            $this->filterRegistry,
            $page,
            $this->apps,
        );
    }

    /**
     * Backward-compatible Twig function.
     *
     * @return mixed|ContentPipeline
     */
    #[AsTwigFunction('pw')]
    public function getProperty(Page $page, string $property = ''): mixed
    {
        $pipeline = $this->get($page);

        if ('' === $property) {
            return $pipeline;
        }

        return $pipeline->getFilteredProperty(ucfirst($property));
    }

    /**
     * Provide legacy Manager for filters that still need it.
     */
    public function getLegacyManager(Page $page): Manager
    {
        return $this->legacyManagerPool->getManager($page);
    }
}
