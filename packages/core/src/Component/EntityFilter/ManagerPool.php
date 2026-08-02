<?php

namespace Pushword\Core\Component\EntityFilter;

use Exception;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;

/**
 * Legacy entry point to the filtered properties of a page: it hands out the
 * {@see Manager} facade of the page's {@see \Pushword\Core\Content\ContentPipeline}.
 * New code asks {@see ContentPipelineFactory} directly.
 */
final readonly class ManagerPool
{
    public function __construct(
        private ContentPipelineFactory $factory,
    ) {
    }

    public function getManager(Page $page): Manager
    {
        return $this->factory->getLegacyManager($page);
    }

    /**
     * @return mixed|Manager
     */
    public function getProperty(Page $page, string $property = ''): mixed
    {
        $manager = $this->getManager($page);

        if ('' === $property) {
            return $manager;
        }

        if (! method_exists($manager, $property)) {
            throw new Exception('Property `'.$property."` doesn't exist");
        }

        return $manager->$property(); // @phpstan-ignore-line
    }
}
