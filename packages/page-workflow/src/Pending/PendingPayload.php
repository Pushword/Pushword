<?php

namespace Pushword\PageWorkflow\Pending;

use Pushword\Core\Entity\Page;

/**
 * Field set carried by a PendingModification. Centralizes the list of editorial
 * fields so payload construction and apply-on-page logic stay in sync.
 */
final class PendingPayload
{
    /** @var list<string> */
    public const array FIELDS = ['h1', 'mainContent', 'title', 'name', 'metaRobots'];

    /**
     * @return array<string, string>
     */
    public static function snapshotFromPage(Page $page): array
    {
        return [
            'h1' => $page->getH1(),
            'mainContent' => $page->getMainContent(),
            'title' => $page->title,
            'name' => $page->name,
            'metaRobots' => $page->metaRobots,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function applyOnPage(Page $page, array $payload): void
    {
        if (isset($payload['h1']) && is_string($payload['h1'])) {
            $page->setH1($payload['h1']);
        }

        if (isset($payload['mainContent']) && is_string($payload['mainContent'])) {
            $page->setMainContent($payload['mainContent']);
        }

        if (isset($payload['title']) && is_string($payload['title'])) {
            $page->title = $payload['title'];
        }

        if (isset($payload['name']) && is_string($payload['name'])) {
            $page->name = $payload['name'];
        }

        if (isset($payload['metaRobots']) && is_string($payload['metaRobots'])) {
            $page->metaRobots = $payload['metaRobots'];
        }
    }
}
