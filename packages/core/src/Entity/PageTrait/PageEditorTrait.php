<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\User;

trait PageEditorTrait
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $editedBy = null; // @phpstan-ignore-line

    /*
     * @ORM\OneToMany(
     *     targetEntity="Pushword\Core\Entity\PageHasEditor",
     *     mappedBy="page",
     *     cascade={"all"},
     *     orphanRemoval=true
     * )
     * @ORM\OrderBy({"editedAt": "DESC"})
     *
    protected ?ArrayCollection $pageHasEditors;
    /**/

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $createdBy = null; // @phpstan-ignore-line

    #[ORM\Column(type: Types::TEXT, options: ['default' => ''])]
    public string $editMessage = '' {
        set(?string $value) => $this->editMessage = (string) $value;
    }
}
