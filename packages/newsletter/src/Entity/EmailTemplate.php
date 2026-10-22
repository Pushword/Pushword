<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Newsletter\Repository\EmailTemplateRepository;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\Table(name: 'newsletter_email_template')]
#[UniqueEntity('name')]
class EmailTemplate implements IdInterface, Stringable
{
    use IdTrait;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    public string $name = '' {
        set(?string $value) => trim((string) $value);
    }

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $wrapped = true;

    #[ORM\Column(type: Types::TEXT)]
    public string $headerMarkdown = Audience::DEFAULT_EMAIL_HEADER {
        set(?string $value) => (string) $value;
    }

    #[ORM\Column(type: Types::TEXT)]
    public string $footerMarkdown = Audience::DEFAULT_EMAIL_FOOTER {
        set(?string $value) => (string) $value;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
