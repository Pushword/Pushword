<?php

namespace Pushword\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait UserTrait
{
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var string
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\Email(
     *     message="user.email.invalid",
     *     mode="strict"
     * )
     */
    protected $email;

    /**
     * @ORM\Column(type="string", length=150, nullable=true)
     */
    protected $username;

    /**
     * Loaded From BaseUser.
     *
     * @Assert\Length(
     *     min=7,
     *     max=100,
     *     minMessage="user.password.short"
     * )
     */
    protected $plainPassword;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string", nullable=true)
     */
    private $password;

    public function __toString()
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

    public function setPlainPassword($password)
    {
        $this->plainPassword = $password;
        $this->password = '';

        return $this;
    }

    public function getPlainPassword()
    {
        return $this->plainPassword;
    }

    /**
     * @see UserInterface
     */
    public function getRoles()
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = static::ROLE_DEFAULT;

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function hasRole($role)
    {
        return \in_array(strtoupper($role), $this->getRoles(), true);
    }

    /**
        public function __construct()
    {
        $this->roles = [];
    }
    public function addRole($role)
    {
        $role = strtoupper($role);
        if ($role === static::ROLE_DEFAULT) {
            return $this;
        }

        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }


    public function removeRole($role)
    {
        if (false !== $key = array_search(strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }/**/

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the value of username.
     */
    public function getUsername()
    {
        return null !== $this->username ? $this->username : $this->email;
    }

    /**
     * Set the value of username.
     *
     * @return self
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }
}
