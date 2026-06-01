<?php

namespace Pushword\Api\Workflow;

use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;

/**
 * Decides whether a Page edit goes through directly or via a PendingModification.
 *
 * Implemented by the optional `pushword/page-workflow` bundle. When the bundle
 * is not installed, no service implementing this interface is registered and
 * `PageApiController` falls back to direct writes.
 *
 * @phpstan-type GateResult array{
 *   routed: bool,
 *   pendingId?: int|string,
 *   state?: string
 * }
 */
interface WorkflowGateInterface
{
    /**
     * Intercept an incoming edit. Returning ['routed' => false] lets the
     * controller apply the change directly. ['routed' => true, …] means a
     * PendingModification was created and the Page is unchanged.
     *
     * @param array<string, mixed> $frontmatter
     *
     * @return array{routed: bool, pendingId?: int|string, state?: string}
     */
    public function intercept(Page $page, array $frontmatter, ?string $body, User $editor): array;

    /**
     * Read-only editorial state to surface in the GET response frontmatter.
     *
     * @return array<string, mixed>|null
     */
    public function describeEditorial(Page $page): ?array;
}
