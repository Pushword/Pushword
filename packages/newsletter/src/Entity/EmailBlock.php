<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Repository\EmailBlockRepository;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailBlockRepository::class)]
#[ORM\Table(name: 'newsletter_email_block')]
class EmailBlock implements IdInterface, Stringable
{
    use IdTrait;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name = '' {
        set(?string $value) => trim((string) $value);
    }

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    public string $bodyMarkdown = '' {
        set(?string $value) => (string) $value;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
