<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\AdminBlockEditor\BlockEditorFilter;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\EntityFilter\FilterEvent;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig;

/**
 * @template T of object
 */
class EnityFilterSuscriber extends AbstractEventSuscriber
{
    #[Required]
    public Twig $twig;

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.entity_filter.before_filtering' => 'convertJsBlockToHtml',
        ];
    }

    public function convertJsBlockToHtml(FilterEvent $filterEvent): void
    {
        $page = $filterEvent->getManager()->page;

        if ('MainContent' !== $filterEvent->getProperty()) {
            return;
        }

        if (! $this->mayUseEditorBlock($page)) {
            return;
        }

        if (true === ($appConfig = $this->apps->get($page->getHost()))->get('admin_block_editor_disable_listener')) {
            return;
        }

        $this->removeMarkdownFilter($appConfig);

        $blockEditorFilter = new BlockEditorFilter();
        $blockEditorFilter->app = $appConfig;
        $blockEditorFilter->page = $page;
        $blockEditorFilter->twig = $this->twig;

        $page->setMainContent($blockEditorFilter->apply($page->getMainContent()));
    }

    private function removeMarkdownFilter(AppConfig $appConfig): void
    {
        $filters = $appConfig->getFilters();
        $filters['main_content'] = str_replace(',markdown', '', $filters['main_content']);
        $appConfig->setFilters($filters);
    }
}
