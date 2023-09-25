<?php

namespace Pushword\Core\Entity\MediaTrait;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Utils\F;
use Pushword\Core\Utils\SafeMediaMimeType;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

trait MediaTrait
{
    use ImageTrait;
    use MediaHashTrait;
    use MediaNameTrait;
    use MediaSlugTrait;
    use TimestampableTrait;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    protected string $storeIn = '';

    /**
     * Used to abstract storeIn.
     */
    protected string $projectDir = '';

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, name: 'media')]
    protected ?string $media = null;

    // TODO Rename to filename

    /**
     * NOTE : this is used only for media renaming.
     */
    protected ?string $mediaBeforeUpdate = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50)]
    protected ?string $mimeType = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    protected int $size;

    /**
     * @var UploadedFile|File|null
     */
    #[Vich\UploadableField(mapping: 'media_media', fileNameProperty: 'slug', mimeType: 'mimeType', size: 'size', dimensions: 'dimensions')]
    protected $mediaFile;

    // todo Rename to $file
    /**
     * @var \Doctrine\Common\Collections\Collection<\Pushword\Core\Entity\PageInterface>|null
     */
    #[ORM\OneToMany(targetEntity: PageInterface::class, mappedBy: 'mainImage')]
    protected ?Collection $mainImagePages = null; // @phpstan-ignore-line

    public function setProjectDir(string $projectDir): self
    {
        $this->projectDir = $projectDir;

        return $this;
    }

    /** @psalm-suppress InvalidReturnType */
    protected function extractExtension(string $string): string
    {
        if (! str_contains($string, '.')) {
            return '';
        }

        if (0 === preg_match('#.*(\.[^.\s]{3,4})$#', $string)) {
            return '';
        }

        return F::preg_replace_str('/.*(\\.[^.\\s]{3,4})$/', '$1', $string);
    }

    private function extractExtensionFromFile(): string
    {
        if (null === $this->getMediaFile()) {
            throw new \Exception();
        }

        $extension = $this->getMediaFile()->guessExtension(); // From MimeType
        $extension = null === $extension ? '' : '.'.$extension;

        return $this->fixExtension($extension);
    }

    /**
     * Because for some format, the mime type extension is not the best.
     */
    private function fixExtension(string $extension): string
    {
        // Todo : when using guessExtension, it's using safe mymetype and returning gpx as txt
        if ('.xml' !== $extension) {
            return $extension;
        }

        if ('.gpx' !== $this->extractExtension($this->getMediaFileName())) {
            return $extension;
        }

        return '.gpx';
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $executionContext): void
    {
        if (null === $this->getMimeType()) {
            return;
        }

        if (null === $this->mediaFile) {
            return;
        }

        if ($this->mediaFile->getMimeType() == $this->getMimeType()) {
            return;
        }

        $executionContext
            ->buildViolation("Attention ! Vous essayez de remplacer un fichier d'un type ("
                .$this->getMimeType().") par un fichier d'une autre type (".$this->mediaFile->getMimeType().')')
            ->atPath('fileName')
            ->addViolation()
        ;
    }

    public function setMediaFile(File $file = null): void
    {
        $this->mediaFile = $file;

        if (null !== $file) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getMediaFile(): ?File
    {
        return $this->mediaFile;
    }

    public function getMediaFileName(): string
    {
        if (! $this->mediaFile instanceof \Symfony\Component\HttpFoundation\File\File) {
            throw new \Exception('MediaFile is not setted');
        }

        if ($this->mediaFile instanceof UploadedFile) {
            return $this->mediaFile->getClientOriginalName();
        }

        return $this->mediaFile->getFilename();
    }

    public function getMedia(): ?string
    {
        return $this->media;
    }

    public function setMedia(?string $media): self
    {
        if (null === $media) {
            return $this;
        }

        if (null !== $this->media) {
            $this->setMediaBeforeUpdate($this->media);
        }

        $this->media = $media;

        return $this;
    }

    public function getStoreIn(): ?string
    {
        if ('' === $this->projectDir) {
            throw new \Exception('must set project dir before');
        }

        return str_replace('%kernel.project_dir%', $this->projectDir, $this->storeIn);
    }

    public function setStoreIn(string $pathToDir): self
    {
        if ('' === $this->projectDir) {
            throw new \Exception('must set project dir before');
        }

        $this->storeIn = rtrim(str_replace($this->projectDir, '%kernel.project_dir%', $pathToDir), '/');

        return $this;
    }

    public function getPath(): string
    {
        if (null === $this->media) {
            throw new \LogicException();
        }

        return $this->getStoreIn().'/'.$this->media;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $uploadedFile = $this->getMediaFile();
        if ($uploadedFile instanceof UploadedFile
            && \in_array($uploadedFile->getClientMimeType(), SafeMediaMimeType::get(), true)) {
            $mimeType = $uploadedFile->getClientMimeType();
        }

        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = (int) $size;

        return $this;
    }

    /**
     * @return Collection<int, PageInterface>
     */
    public function getMainImagePages(): Collection
    {
        return $this->mainImagePages ?? throw new \Exception();
    }

    #[ORM\PreRemove]
    public function removeMainImageFromPages(): void
    {
        if ($this->mainImagePages) { // @phpstan-ignore-line
            foreach ($this->mainImagePages as $page) {
                $page->setMainImage(null);
            }
        }
    }

    /**
     * this is used only for media renaming.
     */
    public function getMediaBeforeUpdate(): ?string
    {
        return $this->mediaBeforeUpdate;
    }

    /**
     * this is used only for media renaming.
     *
     * @param string|null $mediaBeforeUpdate NOTE : this is used only for media renaming
     */
    public function setMediaBeforeUpdate(?string $mediaBeforeUpdate): self
    {
        if (null === $this->mediaBeforeUpdate || null === $mediaBeforeUpdate) {
            $this->mediaBeforeUpdate = $mediaBeforeUpdate;
        }

        return $this;
    }
}
