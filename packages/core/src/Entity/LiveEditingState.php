<?php

declare(strict_types=1);

namespace Pushword\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'live_editing_state')]
class LiveEditingState
{
    #[ORM\Column(length: 32)]
    public string $room;

    #[ORM\Column(length: 64)]
    public string $revision;

    #[ORM\Column]
    public int $cursor = 0;

    #[ORM\Column(type: Types::TEXT, length: 4_194_304, nullable: true)]
    public ?string $snapshot = null;

    #[ORM\Column(nullable: true)]
    public ?int $groupStartedAt = null;

    #[ORM\Column(nullable: true)]
    public ?int $lastSavedAt = null;

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
        $this->groupStartedAt = null;
        $this->lastSavedAt = null;
    }
}
