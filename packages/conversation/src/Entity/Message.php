<?php

namespace Pushword\Conversation\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=MessageRepository::class)
 */
class Message
{
    use HostTrait;
    use IdTrait;
    use TimestampableTrait;

    /**
     * @var string
     * @ORM\Column(type="string", length=180, nullable=true)
     */
    protected $authorName = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=180, nullable=true)
     */
    protected $authorEmail = '';

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $authorIp;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     * @Assert\NotBlank
     * @Assert\Length(
     *     min=2,
     *     max=200000,
     *     minMessage="conversation.content.short",
     *     maxMessage="conversation.content.long"
     * )
     */
    protected $content;

    /**
     * Identifier referring (most of time, URI).
     *
     * @ORM\Column(type="string", length=180)
     *
     * @var string
     */
    protected $referring;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $publishedAt;

    public function __construct()
    {
        $this->updatedAt = null !== $this->updatedAt ? $this->updatedAt : new \DateTime();
        $this->createdAt = null !== $this->createdAt ? $this->createdAt : new \DateTime();
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /**
     * Set message content.
     */
    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get message content.
     *
     * @return string|null
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get the value of authorName.
     *
     * @return string
     */
    public function getAuthorName()
    {
        return $this->authorName;
    }

    /**
     * Set the value of authorName.
     *
     * @return self
     */
    public function setAuthorName(?string $authorName)
    {
        $this->authorName = $authorName;

        return $this;
    }

    /**
     * Get the value of authorEmail.
     *
     * @return string
     */
    public function getAuthorEmail()
    {
        return $this->authorEmail;
    }

    /**
     * Set the value of authorEmail.
     *
     * @return self
     */
    public function setAuthorEmail(?string $authorEmail)
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    /**
     * Get identifier referring.
     *
     * @return string
     */
    public function getReferring()
    {
        return $this->referring;
    }

    /**
     * Set identifier referring.
     *
     * @param string $from Identifier referring
     *
     * @return self
     */
    public function setReferring(string $referring)
    {
        $this->referring = $referring;

        return $this;
    }

    /**
     * Get the value of authorIp.
     *
     * @return int
     */
    public function getAuthorIp()
    {
        return $this->authorIp;
    }

    /**
     * Set the value of authorIp.
     *
     * @return self
     */
    public function setAuthorIp(?int $authorIp)
    {
        if (null !== $authorIp) {
            $this->authorIp = $authorIp;
        }

        return $this;
    }

    public function setAuthorIpRaw(string $authorIp)
    {
        return $this->setAuthorIp(ip2long(IPUtils::anonymize($authorIp)));
    }

    public function getAuthorIpRaw()
    {
        return long2ip($this->getAuthorIp());
    }

    public function __toString()
    {
        return $this->id.' ';
    }
}
