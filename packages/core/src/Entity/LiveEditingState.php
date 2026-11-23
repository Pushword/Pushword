<?php

declare(strict_types=1);

namespace Pushword\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'live_editing_state')]
class LiveEditingState
{
    private const int MAX_ANCHOR_BYTES = 4_194_304;

    private const int MAX_ANCHORS = 64;

    #[ORM\Column(length: 32)]
    public string $room;

    #[ORM\Column(length: 64)]
    public string $revision;

    #[ORM\Column(name: 'sync_cursor', options: ['default' => 0])]
    public int $cursor = 0;

    #[ORM\Column(type: Types::TEXT, length: 4_194_304, nullable: true)]
    public ?string $snapshot = null;

    #[ORM\Column(nullable: true)]
    public ?int $groupStartedAt = null;

    #[ORM\Column(nullable: true)]
    public ?int $lastSavedAt = null;

    /** @var array<array-key, array{baseline: ?string, snapshot: ?string}>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $anchors = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    public private(set) int $writeVersion = 1;

    public function __construct(#[ORM\Id]
        #[ORM\Column(length: 64)]
        public string $document, string $revision)
    {
        $this->restart($revision);
    }

    public function restart(string $revision): void
    {
        $this->room = bin2hex(random_bytes(16));
        $this->revision = $revision;
        $this->cursor = 0;
        $this->snapshot = null;
        $this->anchors = [];
        $this->groupStartedAt = null;
        $this->lastSavedAt = null;
    }

    public function remember(string $baseline): void
    {
        $anchor = ['baseline' => null === $this->snapshot ? $baseline : null, 'snapshot' => $this->snapshot];
        if ($this->anchorSize($anchor) > self::MAX_ANCHOR_BYTES) {
            return;
        }

        $this->anchors ??= [];
        $this->anchors[$this->revision] = $anchor;

        // Keep the opening revision for editors who started before the room,
        // plus recent revisions under a per-document storage budget.
        $first = array_key_first($this->anchors);
        $bytes = 0;
        foreach ($this->anchors as $anchor) {
            $bytes += $this->anchorSize($anchor);
        }

        foreach ($this->anchors as $revision => $anchor) {
            if (\count($this->anchors) <= self::MAX_ANCHORS && $bytes <= self::MAX_ANCHOR_BYTES) {
                break;
            }

            if ($revision !== $first) {
                unset($this->anchors[$revision]);
                $bytes -= $this->anchorSize($anchor);
            }
        }
    }

    /** @param array{baseline: ?string, snapshot: ?string} $anchor */
    private function anchorSize(array $anchor): int
    {
        return \strlen($anchor['baseline'] ?? '') + \strlen($anchor['snapshot'] ?? '');
    }
}
