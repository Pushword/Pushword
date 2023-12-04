<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageInterface;

trait PageI18nTrait
{
    /**
     * //rfc5646.
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 5)]
    protected string $locale = '';

    /**
     * @var Collection<string, PageInterface>|null
     */
    #[ORM\ManyToMany(targetEntity: \Pushword\Core\Entity\PageInterface::class)]
    protected ?Collection $translations = null;  // @phpstan-ignore-line

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @param Collection<string, PageInterface> $translations
     */
    public function setTranslations(Collection $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    /**
     * @return Collection<string, PageInterface>
     */
    public function getTranslations(): Collection
    {
        return $this->translations ?? ($this->translations = new ArrayCollection());
    }

    public function addTranslation(PageInterface $page, bool $recursive = true): self
    {
        if (! $this->getTranslations()->contains($page) && $this != $page) {
            $this->getTranslations()->add($page);
        }

        // Add the other ('ever exist') translations to the new added Translation
        if ($recursive) {
            foreach ($this->getTranslations() as $otherTranslation) {
                $page->addTranslation($otherTranslation, false);
            }
        }

        // Reversing the syncing
        // Add this Page to the translated Page
        // + Add the translated Page to the other translation
        if ($recursive) {
            $page->addTranslation($this, false);

            foreach ($this->getTranslations() as $otherTranslation) {
                if ($otherTranslation == $this) {  // déjà fait
                    continue;
                }

                if ($otherTranslation == $page) { // on ne se référence pas soit-même
                    continue;
                }

                $otherTranslation->addTranslation($page, false);
            }
        }

        return $this;
    }

    public function removeTranslation(PageInterface $page, bool $recursive = true): self
    {
        if ($this->getTranslations()->contains($page)) {
            $this->getTranslations()->removeElement($page);

            if ($recursive) {
                foreach ($this->getTranslations() as $otherTranslation) {
                    $page->removeTranslation($otherTranslation, false);
                }
            }
        }

        if ($recursive) {
            $page->removeTranslation($this, false);

            foreach ($this->getTranslations() as $otherTranslation) {
                if ($otherTranslation == $this) {
                    continue;
                }

                if ($otherTranslation == $page) {
                    continue;
                }

                $otherTranslation->removeTranslation($page, false);
            }
        }

        return $this;
    }
}
