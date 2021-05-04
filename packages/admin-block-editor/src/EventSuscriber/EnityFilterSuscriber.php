<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\AdminBlockEditor\BlockEditorFilter;
use Pushword\Core\Component\App\AppConfig;
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
        $app = $this->apps->get($page->getHost());

        if (! $page instanceof PageInterface
            || 'MainContent' != $event->getProperty()
            || ! $this->mayUseEditorBlock($page)
            || true === $app->get('admin_block_editor_disable_listener')) {
            return;
        }

        $this->removeMarkdownFilter($app);

        $blockEditorFilter = (new BlockEditorFilter())
            ->setApp($app)
            ->setEntity($page)
            ->setTwig($this->twig)
        ;

        $page->setMainContent($blockEditorFilter->apply($page->getMainContent()));
        //dump($page->getMainContent());
    }

    private function removeMarkdownFilter(AppConfig $app): void
    {
        $filters = $app->getFilters();
        $filters['main_content'] = str_replace(',markdown', '', $filters['main_content']);
        $app->setFilters($filters);
    }
}
