<?php

namespace Pushword\Core\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait UserTrait
{
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected DateTimeInterface $createdAt;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\Email(
     *     message="user.email.invalid",
     *     mode="strict"
     * )
     */
    protected string $email = '';

    /**
     * @ORM\Column(type="string", length=150, nullable=true)
     */
    protected ?string $username = null;

    /**
     * Loaded From BaseUser.
     *
     * @Assert\Length(
     *     min=7,
     *     max=100,
     *     minMessage="user.password.short"
     * )
     */
    protected ?string $plainPassword = null;

    /**
     * @ORM\Column(type="json")
     *
     * @var string[]
     */
    private array $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string", nullable=true)
     */
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

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    /**
     *  @return string[] The user roles
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = UserInterface::ROLE_DEFAULT;

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return \in_array(strtoupper($role), $this->getRoles(), true);
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     *
     * @return string
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
        return '';
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    /**
     * @ORM\PrePersist
     */
    public function updatedTimestamps(): self
    {
        $this->setCreatedAt(new \DateTime('now'));

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
        return null !== $this->username ? $this->username : $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    /**
     * Set the value of username.
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }
}
