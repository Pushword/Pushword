<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait PageI18nTrait
{
    /**
     * //rfc5646.
     *
     * @ORM\Column(type="string", length=5)
     */
    protected string $locale = '';

    public function __constructI18n()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * @ORM\ManyToMany(targetEntity="Pushword\Core\Entity\PageInterface")
     */
    protected $translations;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale($locale): self
    {
        $this->locale = (string) $locale;

        return $this;
    }

    public function setTranslations($translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(self $translation, $recursive = true): self
    {
        if (! $this->translations->contains($translation) && $this != $translation) {
            $this->translations[] = $translation;
        }

        // Add the other ('ever exist') translations to the new added Translation
        if (true === $recursive) {
            foreach ($this->translations as $otherTranslation) {
                $translation->addTranslation($otherTranslation, false);
            }
        }

        // Reversing the syncing
        // Add this Page to the translated Page
        // + Add the translated Page to the other translation
        if (true === $recursive) {
            $translation->addTranslation($this, false);

            foreach ($this->translations as $otherTranslation) {
                if ($otherTranslation != $this // déjà fait
                    && $otherTranslation != $translation // on ne se référence pas soit-même
                ) {
                    $otherTranslation->addTranslation($translation, false);
                }
            }
        }

        return $this;
    }

    public function removeTranslation(self $translation, $recursive = true): self
    {
        if ($this->translations->contains($translation)) {
            $this->translations->removeElement($translation);

            if (true === $recursive) {
                foreach ($this->translations as $otherTranslation) {
                    $translation->removeTranslation($otherTranslation, false);
                }
            }
        }

        if (true === $recursive) {
            $translation->removeTranslation($this, false);

            foreach ($this->translations as $otherTranslation) {
                if ($otherTranslation != $this // déjà fait
                    && $otherTranslation != $translation // on ne se déréférence pas soit-même
                ) {
                    $otherTranslation->removeTranslation($translation, false);
                }
            }
        }

        return $this;
    }
}
