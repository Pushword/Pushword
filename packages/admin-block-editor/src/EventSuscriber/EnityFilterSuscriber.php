<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\AdminBlockEditor\BlockEditorFilter;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\EntityFilter\FilterEvent;
use Pushword\Core\Entity\PageInterface;
use Twig\Environment as Twig;

/**
 * @template T of object
 */
class EnityFilterSuscriber extends AbstractEventSuscriber
{
    #[\Symfony\Contracts\Service\Attribute\Required]
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

    /**
     * @param FilterEvent<T> $filterEvent
     */
    public function convertJsBlockToHtml(FilterEvent $filterEvent): void
    {
        $page = $filterEvent->getManager()->getEntity();
        if (! $page instanceof PageInterface) {
            return;
        }

        if ('MainContent' != $filterEvent->getProperty()) {
            return;
        }

        if (! $this->mayUseEditorBlock($page)) {
            return;
        }

        if (true === ($appConfig = $this->apps->get($page->getHost()))->get('admin_block_editor_disable_listener')) {
            return;
        }

        $this->removeMarkdownFilter($appConfig);

        $blockEditorFilter = (new BlockEditorFilter())
            ->setApp($appConfig)
            ->setEntity($page)
            ->setTwig($this->twig)
        ;

        $page->setMainContent($blockEditorFilter->apply($page->getMainContent())); // @phpstan-ignore-line
    }

    private function removeMarkdownFilter(AppConfig $appConfig): void
    {
        $filters = $appConfig->getFilters();
        $filters['main_content'] = str_replace(',markdown', '', $filters['main_content']);
        $appConfig->setFilters($filters);
    }
}
