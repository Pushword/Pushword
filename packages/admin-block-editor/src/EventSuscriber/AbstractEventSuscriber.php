<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEventSuscriber implements EventSubscriberInterface
{
    /** @required */
    public AppPool $apps;

    public bool $editorBlockForNewPage;

    public function __construct(bool $editorBlockForNewPage)
    {
        $this->editorBlockForNewPage = $editorBlockForNewPage;
    }

    protected function mayUseEditorBlock(?PageInterface $page): bool
    {
        if (null !== $page && '' !== $page->getMainContent() && false === json_decode($page->getMainContent())) {
            return false;
        }

        if (null !== $page && '' !== $page->getHost() && \is_bool($this->apps->get($page->getHost())->get('admin_block_editor'))) {
            return $this->apps->get($page->getHost())->get('admin_block_editor');
        }

        return $this->editorBlockForNewPage;
    }
}
