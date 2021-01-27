<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\AdminBlockEditor\BlockEditorFilter;
use Pushword\Core\Component\EntityFilter\FilterEvent;
use Pushword\Core\Entity\PageInterface;
use Twig\Environment as Twig;

class EnityFilterSuscriber extends AbstractEventSuscriber
{
    /** @required */
    public Twig $twig;

    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.entity_filter.before_filtering' => 'convertJsBlockToHtml',
        ];
    }

    public function convertJsBlockToHtml(FilterEvent $event): void
    {
        $page = $event->getManager()->getEntity();

        if (! $page instanceof PageInterface
            || 'MainContent' != $event->getProperty()
            || ! $this->mayUseEditorBlock($page)
            || true === $this->apps->get($page->getHost())->get('admin_block_editor_disable_listener')) {
            return;
        }

        $blockEditorFilter = (new BlockEditorFilter())
            ->setApp($this->apps->get($page->getHost()))
            ->setEntity($page)
            ->setTwig($this->twig)
        ;

        $page->setMainContent($blockEditorFilter->apply($page->getMainContent()));
    }
}
