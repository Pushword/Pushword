<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEventSuscriber implements EventSubscriberInterface
{
    /** @required */
    public AppPool $apps;

    /** @required */
    public $editorBlockForNewPage;

    public function __construct($editorBlockForNewPage)
    {
        $this->editorBlockForNewPage = $editorBlockForNewPage;
    }

    protected function mayUseEditorBlock(?PageInterface $page): bool
    {
        if ($page && ! empty($page->getMainContent()) && ! json_decode($page->getMainContent())) {
            return false;
        }

        if ($page && $page->getHost() && \is_bool($this->apps->get($page->getHost())->get('admin_block_editor'))) {
            return $this->apps->get($page->getHost())->get('admin_block_editor');
        }

        return $this->editorBlockForNewPage;
    }
}
