<?php

namespace Pushword\Core\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Repository\LoginTokenRepository;

#[ORM\Entity(repositoryClass: LoginTokenRepository::class)]
#[ORM\Table(name: 'login_token')]
class LoginToken
{
    public const string TYPE_LOGIN = 'login';

    public const string TYPE_SET_PASSWORD = 'set_password';

    public const int TTL_SECONDS = 3600; // 1 hour

    #[ORM\Id, ORM\Column(type: Types::INTEGER), ORM\GeneratedValue(strategy: 'AUTO')]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $used = false;

    public function __construct(#[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public User $user, #[ORM\Column(type: Types::STRING, length: 20)]
        public string $type = self::TYPE_LOGIN)
    {
        $this->createdAt = new DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+'.self::TTL_SECONDS.' seconds');
    }

    public function setToken(string $plainToken): self
    {
        $this->tokenHash = hash('sha256', $plainToken);

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function verifyToken(string $plainToken): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $plainToken));
    }

    public function isValid(): bool
    {
        return ! $this->used && $this->expiresAt > new DateTimeImmutable();
    }

    public function markUsed(): self
    {
        $this->used = true;

        return $this;
    }
}
