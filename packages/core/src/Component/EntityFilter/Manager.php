<?php

namespace Pushword\Core\Component\EntityFilter;

use Pushword\Core\Content\ContentPipeline;
use Pushword\Core\Entity\Page;

/**
 * Facade kept for the filter API: {@see Filter\FilterInterface::apply()} and
 * {@see FilterEvent} hand a Manager to filters and listeners. Everything it does
 * happens in the {@see ContentPipeline} it wraps — there is one pipeline per page
 * and one Manager per pipeline, so both see the same filtered values.
 *
 * @method string getMainContent()
 */
final class Manager
{
    public function __construct(private readonly ContentPipeline $pipeline)
    {
    }

    public Page $page {
        get => $this->pipeline->page;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getPipeline(): ContentPipeline
    {
        return $this->pipeline;
    }

    /**
     * Magic getter for Entity properties: `$manager->title()` and `getTitle()` both
     * resolve `Title`. The pipeline's own magic getter spells that out, so the two
     * cannot drift.
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $method, array $arguments = []): mixed
    {
        return $this->pipeline->__call($method, $arguments);
    }

    /**
     * @param string[] $filters
     */
    public function applyFilters(bool|float|int|string|null $propertyValue, array $filters, string $property = ''): mixed
    {
        return $this->pipeline->applyFilters($propertyValue, $filters, $property);
    }
}
