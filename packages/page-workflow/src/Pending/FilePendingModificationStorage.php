<?php

namespace Pushword\PageWorkflow\Pending;

use DateTime;
use LogicException;
use Pushword\Core\Entity\Page;

use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\json_encode;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Default storage: writes the pending modification as a JSON snapshot under
 * `<varDir>/page-workflow/<page-id>/pending.json`. Bundles can decorate this
 * via `#[AsDecorator]` to mirror to alternative backends (e.g. flat files).
 */
final readonly class FilePendingModificationStorage implements PendingModificationStorageInterface
{
    private Filesystem $fs;

    public function __construct(private string $varDir)
    {
        $this->fs = new Filesystem();
    }

    public function read(Page $page): ?PendingModification
    {
        $path = $this->pathFor($page);
        if (! $this->fs->exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        /** @var array{pageId: int, workflowState: string, editedBy: ?int, editedAt: string, editMessage: string, payload: array<string, mixed>} $data */
        $data = json_decode($raw, true);

        $modification = new PendingModification(
            pageId: $data['pageId'],
            payload: $data['payload'],
        );
        $modification->workflowState = $data['workflowState'];
        $modification->editedBy = $data['editedBy'];
        $modification->editedAt = new DateTime($data['editedAt']);
        $modification->editMessage = $data['editMessage'];

        return $modification;
    }

    public function write(Page $page, PendingModification $modification): void
    {
        $json = json_encode([
            'pageId' => $modification->pageId,
            'workflowState' => $modification->workflowState,
            'editedBy' => $modification->editedBy,
            'editedAt' => $modification->editedAt->format(\DATE_ATOM),
            'editMessage' => $modification->editMessage,
            'payload' => $modification->payload,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        $this->fs->dumpFile($this->pathFor($page), $json);
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
        if (null === $page->id) {
            throw new LogicException('Cannot resolve pending modification path for an unsaved Page.');
        }

        return $this->varDir.'/page-workflow/'.$page->id.'/pending.json';
    }
}
