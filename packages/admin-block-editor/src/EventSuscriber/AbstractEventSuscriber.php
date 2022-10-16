<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEventSuscriber implements EventSubscriberInterface
{
    #[\Symfony\Contracts\Service\Attribute\Required]
    public AppPool $apps;

    public function __construct(public bool $editorBlockForNewPage)
    {
    }

    /**
     * @noRector
     */
    protected function mayUseEditorBlock(?PageInterface $page): bool
    {
        if (null !== $page && '' !== $page->getMainContent() && null === json_decode($page->getMainContent(), null, 512)) {
            return false;
        }

        if (null !== $page && '' !== $page->getHost() && \is_bool($this->apps->get($page->getHost())->get('admin_block_editor'))) {
            return $this->apps->get($page->getHost())->get('admin_block_editor');
        }

        return $this->editorBlockForNewPage;
    }
}
