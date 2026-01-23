<?php

namespace Pushword\AdminBlockEditor\EventSubscriber;

use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractEventSubscriber implements EventSubscriberInterface
{
    #[Required]
    public AppPool $apps;

    public function __construct(public bool $editorBlockForNewPage)
    {
    }

    /**
     * @param FormEvent<object>|null $event
     */
    protected function mayUseEditorBlock(?Page $page, ?FormEvent $event = null): bool
    {
        if (null !== $event
            && ! $event->getAdmin() instanceof PageCrudController) {
            return false;
        } // do not use event for PageRedirection for example

        // if (null !== $page && '' !== $page->getMainContent() && null === json_decode($page->getMainContent(), null, 512)) {
        //     return false;
        // }

        if (null !== $page && '' !== $page->host) {
            return $this->apps->get($page->host)->getBoolean('admin_block_editor', false);
        }

        return $this->editorBlockForNewPage;
    }
}
