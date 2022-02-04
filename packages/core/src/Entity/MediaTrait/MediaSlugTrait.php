<?php

namespace Pushword\Core\Entity\MediaTrait;

use Cocur\Slugify\Slugify;
use Exception;
use Pushword\Core\Utils\Filepath;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

trait MediaSlugTrait
{
    protected string $slug = '';

    public function setSlug(?string $slug): self
    {
        if ('' !== $this->slug) {
            return $this->changeSlug((string) $slug);
        }

        $this->slug = $this->slugify((string) $slug);

        return $this;
    }

    public function getSlugForce(): string
    {
        return $this->getSlug();
    }

    /**
     * Used by MediaAdmin.
     */
    public function setSlugForce(?string $slug): self
    {
        if ('' === $this->name && null !== $slug) {
            $this->name = $slug;
        }

        return $this->changeSlug((string) $slug);
    }

    private function changeSlug(string $slug): self
    {
        if ('' === $slug) {
            return $this;
        }

        if (null !== $this->getMediaFile()) {
            return $this->setSlugForNewMedia($slug);
        }

        $this->slug = $this->slugify($slug);

        if (null !== $this->media) {
            $this->setMedia($this->slug.$this->extractExtension($this->media));
        }

        return $this;
    }

    /**
     * Used by VichUploader.
     * Permit to setMedia from filename.
     */
    private function setSlugForNewMedia(string $filename): self
    {
        if (null === $this->getMediaFile()) {
            //throw new Exception('debug... thinking setSlug was only used by Vich ???');
            return $this;
        }

        $filename = '' !== $filename ? $filename : $this->getMediaFileName();
        if ('' === $filename) {
            throw new Exception('debug... '); //dd($this->mediaFile);
        }

        $extension = $this->extractExtensionFromFile();

        $slugSlugified = $this->slugifyPreservingExtension($filename, $extension);

        $this->setMedia($slugSlugified);
        $this->slug = \Safe\substr($slugSlugified, 0, \strlen($slugSlugified) - \strlen($extension));

        return $this;
    }

    private function slugify(string $slug): string
    {
        return (new Slugify(['regexp' => '/([^A-Za-z0-9\.]|-)+/']))->slugify($slug);
    }

    protected function slugifyPreservingExtension(string $string, string $extension = ''): string
    {
        $extension = '' === $extension ? $this->extractExtension($string) : $extension;
        $string = str_ends_with($string, $extension) ? \Safe\substr($string, 0, \strlen($string) - \strlen($extension)) : $string;
        $stringSlugify = $this->slugify($string);

        return $stringSlugify.$extension;
    }

    public function getSlug(): string
    {
        if ('' !== $this->slug) {
            return $this->slug;
        }

        if (null !== $this->media) {
            return $this->slug = Filepath::removeExtension($this->media);
        }

        $this->slug = (new Slugify())->slugify($this->getName());

        return $this->slug;
    }
}
