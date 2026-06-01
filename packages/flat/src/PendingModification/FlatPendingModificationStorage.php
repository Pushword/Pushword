<?php

namespace Pushword\Flat\PendingModification;

use DateTime;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\PageWorkflow\Pending\PendingModification;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Pushword\PageWorkflow\Pending\PendingPayload;

use function Safe\file_get_contents;

use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Flat overlay: pending modifications live as `{slug}.pending.md` next to the
 * live `{slug}.md`, so git captures every proposed change. Exclusive — when
 * this implementation is wired up, the inner File storage is not used.
 */
final readonly class FlatPendingModificationStorage implements PendingModificationStorageInterface
{
    private Filesystem $fs;

    public function __construct(
        private FlatFileContentDirFinder $contentDirFinder,
    ) {
        $this->fs = new Filesystem();
    }

    public function read(Page $page): ?PendingModification
    {
        $path = $this->pathFor($page);
        if (! $this->fs->exists($path)) {
            return null;
        }

        $document = YamlFrontMatter::parse(file_get_contents($path));
        /** @var array<string, mixed> $matter */
        $matter = $document->matter();

        $payload = PendingPayload::snapshotFromPage($page);
        foreach (PendingPayload::FIELDS as $field) {
            if ('mainContent' === $field) {
                // YamlFrontMatter keeps the newline after `---`; strip it so the
                // round-trip is byte-identical to what was written.
                $payload[$field] = ltrim($document->body(), "\n");

                continue;
            }

            if (isset($matter[$field]) && is_string($matter[$field])) {
                $payload[$field] = $matter[$field];
            }
        }

        $modification = new PendingModification(
            pageId: (int) $page->id,
            payload: $payload,
        );

        $modification->workflowState = isset($matter['workflowState']) && is_string($matter['workflowState'])
            ? $matter['workflowState']
            : 'draft';

        $modification->editedBy = isset($matter['editedBy']) && is_numeric($matter['editedBy'])
            ? (int) $matter['editedBy']
            : null;

        $modification->editMessage = isset($matter['editMessage']) && is_string($matter['editMessage'])
            ? $matter['editMessage']
            : '';

        if (isset($matter['editedAt']) && is_string($matter['editedAt'])) {
            $modification->editedAt = new DateTime($matter['editedAt']);
        }

        return $modification;
    }

    public function write(Page $page, PendingModification $modification): void
    {
        $metadata = [
            'workflowState' => $modification->workflowState,
            'editedBy' => $modification->editedBy,
            'editedAt' => $modification->editedAt->format(\DATE_ATOM),
            'editMessage' => $modification->editMessage,
        ];

        foreach (PendingPayload::FIELDS as $field) {
            if ('mainContent' === $field) {
                continue;
            }

            if (isset($modification->payload[$field])) {
                $metadata[$field] = $modification->payload[$field];
            }
        }

        $body = isset($modification->payload['mainContent']) && is_string($modification->payload['mainContent'])
            ? $modification->payload['mainContent']
            : '';

        $content = "---\n".Yaml::dump($metadata, 4, 2)."---\n".$body;

        $this->fs->dumpFile($this->pathFor($page), $content);
    }

    public function delete(Page $page): void
    {
        $this->fs->remove($this->pathFor($page));
    }

    public function has(Page $page): bool
    {
        return $this->fs->exists($this->pathFor($page));
    }

    private function pathFor(Page $page): string
    {
        $contentDir = $this->contentDirFinder->get($page->host);

        return $contentDir.'/'.$page->slug.'.pending.md';
    }
}
