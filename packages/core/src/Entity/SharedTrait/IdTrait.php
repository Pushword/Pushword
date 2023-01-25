<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\ORM\Mapping as ORM;

trait IdTrait
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
