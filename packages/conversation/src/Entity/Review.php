<?php

namespace Pushword\Conversation\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Conversation\Repository\MessageRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Review extends Message
{
    #[Assert\Range(min: 1, max: 5)]
    protected ?int $rating = null;

    public function getRating(): ?int
    {
        if (null !== $this->rating) {
            return $this->rating;
        }

        $rating = $this->getCustomProperty('rating');

        return \is_numeric($rating) ? (int) $rating : null;
    }

    public function setRating(?int $rating): self
    {
        if (null === $rating) {
            $this->rating = null;
            $this->removeCustomProperty('rating');

            return $this;
        }

        $this->rating = $rating;
        $this->setCustomProperty('rating', $rating);

        return $this;
    }

    public function getTitle(): string
    {
        $title = $this->getCustomProperty('title');

        return \is_string($title) ? $title : '';
    }

    public function setTitle(?string $title): self
    {
        if (null === $title || '' === trim($title)) {
            $this->removeCustomProperty('title');

            return $this;
        }

        $this->setCustomProperty('title', $title);

        return $this;
    }
}
