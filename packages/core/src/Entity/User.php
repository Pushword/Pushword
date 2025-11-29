<?php

namespace Pushword\Core\Entity;

use DateTime;
use DateTimeInterface;
use Deprecated;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesTrait;
use Pushword\Core\Repository\UserRepository;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('email', message: 'userEmailAlreadyUsed')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, Stringable
{
    use CustomPropertiesTrait;

    /** @var string */
    public const ROLE_DEFAULT = 'ROLE_USER';

    /** @var string */
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    #[ORM\Id, ORM\Column(type: Types::INTEGER), ORM\GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\Email(message: 'userEmailInvalid', mode: 'strict')]
    protected string $email = '';

    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    protected ?string $username = null;

    #[ORM\Column(type: Types::STRING, length: 5, options: ['default' => 'en'])]
    protected string $locale = 'en';

    /**
     * Loaded From BaseUser.
     */
    #[Assert\Length(min: 7, max: 100, minMessage: 'userPasswordShort')]
    protected ?string $plainPassword = null;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $password = null;

    public function __toString(): string
    {
        return $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setPlainPassword(?string $password): self
    {
        $this->plainPassword = $password;
        $this->password = '';

        return $this;
    }

    public function getPlainPassword(): string
    {
        return $this->plainPassword ?? '';
    }

    /**
     * @return string[] The user roles
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = self::ROLE_DEFAULT;

        return array_unique($roles);
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getRolesForListing(): string
    {
        return implode(', ', $this->getRoles());
    }

    public function hasRole(string $role): bool
    {
        return \in_array(strtoupper($role), $this->getRoles(), true);
    }

    /**
     * @see User
     */
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

    /**
     * @see User
     */
    public function getSalt(): string
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
        return '';
    }

    #[Deprecated()]
    public function eraseCredentials(): void
    {
    }

    #[ORM\PrePersist]
    public function updatedTimestamps(): self
    {
        $this->setCreatedAt(new DateTime('now'));

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the value of username.
     */
    public function getUsername(): string
    {
        return $this->username ?? $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername() ?:
            throw new Exception();
    }

    /**
     * Set the value of username.
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }
}
