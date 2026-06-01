<?php

namespace Pushword\PageWorkflow\Pending;

use Pushword\Core\Entity\Page;

/**
 * Persistence boundary for a Page's PendingModification. At most one pending
 * modification per page (the workflow handles the lifecycle).
 *
 * Default implementation persists JSON snapshots under var/log; bundles (notably
 * pushword/flat) decorate this to mirror the pending state to filesystem (and git).
 */
interface PendingModificationStorageInterface
{
    public function read(Page $page): ?PendingModification;

    public function write(Page $page, PendingModification $modification): void;

    public function delete(Page $page): void;

    public function has(Page $page): bool;
}
