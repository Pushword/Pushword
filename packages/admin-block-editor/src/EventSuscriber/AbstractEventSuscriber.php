<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\PageAdmin;
use Pushword\Admin\PageCheatSheetAdmin;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractEventSuscriber implements EventSubscriberInterface
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    #[Required]
    public AppPool $apps;

    public function __construct(public bool $editorBlockForNewPage)
    {
    }

    /**
     * @param FormEvent<Page> $event
     */
    protected function mayUseEditorBlock(?Page $page, ?FormEvent $event = null): bool
    {
        if (null !== $event
            && ! $event->getAdmin() instanceof PageAdmin
            && ! $event->getAdmin() instanceof PageCheatSheetAdmin) {
            return false;
        } // do not use event for PageRedirection for example

        if (null !== $page && '' !== $page->getMainContent() && null === json_decode($page->getMainContent(), null, 512)) {
            return false;
        }

        if (null !== $page && '' !== $page->getHost()) {
            return $this->apps->get($page->getHost())->getBoolean('admin_block_editor', false);
        }

        return $this->editorBlockForNewPage;
    }
}
