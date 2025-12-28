<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Page;

trait PageI18nTrait
{
    /**
     * //rfc5646.
     */
    #[ORM\Column(type: Types::STRING, length: 5)]
    public string $locale = '';

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @var Collection<int, Page>
     */
    #[ORM\ManyToMany(targetEntity: Page::class)]
    protected ?Collection $translations = null;  // @phpstan-ignore-line

    /** @param Collection<int, Page> $translations */
    public function setTranslations(Collection $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    /** @return Collection<int, Page> */
    public function getTranslations(): Collection
    {
        return $this->translations ?? ($this->translations = new ArrayCollection());
    }

    public function getTranslation(string $locale): ?Page
    {
        foreach ($this->getTranslations() as $translation) {
            if ($translation->locale === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(Page $page, bool $recursive = true): self
    {
        if (! $this->getTranslations()->contains($page) && $this !== $page) {
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
                if ($otherTranslation === $this) {  // déjà fait
                    continue;
                }

                if ($otherTranslation === $page) { // on ne se référence pas soit-même
                    continue;
                }

                $otherTranslation->addTranslation($page, false);
            }
        }

        return $this;
    }

    public function removeTranslation(Page $page, bool $recursive = true): self
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
                if ($otherTranslation === $this) {
                    continue;
                }

                if ($otherTranslation === $page) {
                    continue;
                }

                $otherTranslation->removeTranslation($page, false);
            }
        }

        return $this;
    }
}
