<?php

namespace Pushword\Core\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Pushword\Core\Entity\SharedTrait\ExtensiblePropertiesTrait;
use Pushword\Core\Repository\UserRepository;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Email;

/**
 * Traits: ExtensiblePropertiesTrait (JSON key-value bag).
 *
 * Key fields: email (unique, identifier), username, locale, roles (JSON array), password (hashed),
 *   apiToken (64-char hex, for Media API auth), createdAt.
 * Designed as MappedSuperclass — end-user projects extend this class (e.g. App\Entity\User).
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('email', message: 'userEmailAlreadyUsed')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, Stringable
{
    use ExtensiblePropertiesTrait;

    public const string PASSWORD_CHANGE_REQUIRED = 'passwordChangeRequired';

    public const string ROLE_DEFAULT = 'ROLE_USER';

    public const string ROLE_PASSWORD_CHANGE = 'ROLE_PASSWORD_CHANGE';

    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    #[ORM\Id, ORM\Column(type: Types::INTEGER), ORM\GeneratedValue(strategy: 'AUTO')]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Email(message: 'userEmailInvalid', mode: Email::VALIDATION_MODE_STRICT)]
    public string $email = '';

    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    public ?string $username = null;

    #[ORM\Column(type: Types::STRING, length: 5, options: ['default' => 'en'])]
    public string $locale = 'en';

    #[Assert\Length(min: 7, max: 100, minMessage: 'userPasswordShort')]
    private ?string $plainPassword = null;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true, nullable: true)]
    public ?string $apiToken = null;

    public function generateApiToken(): self
    {
        $this->apiToken = bin2hex(random_bytes(32));

        return $this;
    }

    public function revokeApiToken(): self
    {
        $this->apiToken = null;

        return $this;
    }

    public function __toString(): string
    {
        return $this->email;
    }

    public function setPlainPassword(?string $password): self
    {
        if (null === $password || '' === $password) {
            $this->plainPassword = null;

            return $this;
        }

        $this->plainPassword = $password;
        $this->password = '';

        return $this;
    }

    public function getPlainPassword(): string
    {
        return $this->plainPassword ?? '';
    }

    /** @return string[] */
    public function getRoles(): array
    {
        if ($this->requiresPasswordChange()) {
            return [self::ROLE_PASSWORD_CHANGE];
        }

        $roles = $this->roles;
        $roles[] = self::ROLE_DEFAULT;

        return array_unique($roles);
    }

    /** @param string[] $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /** @return string[] */
    public function getManagedPropertyKeys(): array
    {
        return [self::PASSWORD_CHANGE_REQUIRED, ...array_keys($this->runtimeManagedKeys)];
    }

    public function requirePasswordChange(): self
    {
        $this->customProperties[self::PASSWORD_CHANGE_REQUIRED] = true;

        return $this;
    }

    public function completePasswordChange(): self
    {
        unset($this->customProperties[self::PASSWORD_CHANGE_REQUIRED]);

        return $this;
    }

    public function requiresPasswordChange(): bool
    {
        return true === ($this->customProperties[self::PASSWORD_CHANGE_REQUIRED] ?? false);
    }

    public function getRolesForListing(): string
    {
        return implode(', ', $this->getRoles());
    }

    public function hasRole(string $role): bool
    {
        return \in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        $this->plainPassword = null;

        return $this;
    }

    #[ORM\PrePersist]
    public function updatedTimestamps(): self
    {
        $this->createdAt = new DateTime('now');

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username ?? $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?: throw new Exception();
    }
}
