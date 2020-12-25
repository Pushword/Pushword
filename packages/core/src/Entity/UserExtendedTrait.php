<?php

// todo: supprimer ! (données qui reviendront via ReservationBundle)

namespace Pushword\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait UserExtendedTrait
{
    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     * @Assert\Length(
     *     min=2,
     *     max=50,
     *     minMessage="user.firstname.short",
     *     maxMessage="user.firstname.long"
     * )
     * @Assert\Regex(
     *     pattern="/^[a-z\-0-9\s]+$/i",
     *     message="user.firstname.invalid"
     * )
     */
    private $firstname;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     * @Assert\Length(
     *     min=2,
     *     max=50,
     *     minMessage="user.name.short",
     *     maxMessage="user.name.long"
     * )
     * @Assert\Regex(
     *     pattern="/^[a-z\-0-9\s]+$/i",
     *     message="user.lastname.invalid"
     * )
     */
    private $lastname;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     * @Assert\Length(
     *     min=2,
     *     max=64,
     *     minMessage="user.phone.short",
     *     maxMessage="user.phone.long"
     * )
     */
    private $phone;

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }
}
