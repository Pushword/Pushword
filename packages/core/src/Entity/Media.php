<?php

namespace Pushword\Core\Entity;

use Cocur\Slugify\Slugify;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use LogicException;
use Pushword\Core\Entity\MediaTrait\ImageTrait;
use Pushword\Core\Entity\MediaTrait\MediaHashTrait;
use Pushword\Core\Entity\MediaTrait\MediaNameTrait;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\SafeMediaMimeType;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * #UniqueEntity({"media"}, message="Media name is ever taken by another media.")
 * #UniqueEntity({"name"}, message="Name is ever taken by another media..").
 */
#[Vich\Uploadable]
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
class Media implements IdInterface
{
    use CustomPropertiesTrait;
    use IdTrait;
    use ImageTrait;
    use MediaHashTrait;
    use MediaNameTrait;
    use TimestampableTrait;

    public function __construct()
    {
        $this->mainImagePages = new ArrayCollection();
        $this->updatedAt ??= new DateTime();
        $this->createdAt ??= new DateTime();
    }

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $storeIn = '';

    /**
     * Used to abstract storeIn.
     */
    protected string $projectDir = '';

    #[ORM\Column(type: Types::STRING, length: 255, name: 'media')]
    protected string $media = ''; // eg : my-file.jpg

    // TODO Rename to filename

    /**
     * NOTE : this is used only for media renaming.
     */
    protected string $mediaBeforeUpdate = '';

    #[ORM\Column(type: Types::STRING, length: 50)]
    protected ?string $mimeType = null; // @phpstan-ignore-line

    #[ORM\Column(type: Types::INTEGER)]
    protected int $size = 0;

    /**
     * @var UploadedFile|File|null
     */
    #[Vich\UploadableField(mapping: 'media_media', fileNameProperty: 'slug', mimeType: 'mimeType', size: 'size', dimensions: 'dimensions')]
    protected $mediaFile;

    /**
     * @var Collection<int, Page>
     */
    #[ORM\OneToMany(targetEntity: Page::class, mappedBy: 'mainImage')]
    protected ?Collection $mainImagePages; // @phpstan-ignore-line TODO drop Page

    public function setProjectDir(string $projectDir): self
    {
        $this->projectDir = $projectDir;

        return $this;
    }

    protected function extractExtension(string $string): string
    {
        if (! str_contains($string, '.')) {
            return '';
        }

        if (0 === preg_match('#.*(\.[^.\s]{3,4})$#', $string)) {
            return '';
        }

        return preg_replace('/.*(\\.[^.\\s]{3,4})$/', '$1', $string) ?? throw new Exception();
    }

    private function extractExtensionFromFile(): string
    {
        $mediaFile = $this->getMediaFile();

        if (null === $mediaFile) {
            throw new Exception();
        }

        $extension = $mediaFile->guessExtension(); // From MimeType
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

        $mediaFileMimeType = $this->mediaFile->getMimeType() ?? 'unknowMimeType';

        if ($mediaFileMimeType === $this->getMimeType()) {
            return;
        }

        $executionContext
            ->buildViolation("Attention ! Vous essayez de remplacer un fichier d'un type ("
                .$mediaFileMimeType.") par un fichier d'une autre type (".$mediaFileMimeType.')')
            ->atPath('fileName')
            ->addViolation()
        ;
    }

    public function setMediaFile(?File $file = null): void
    {
        $this->mediaFile = $file;

        if (null !== $file) {
            $this->updatedAt = new DateTime();
        }
    }

    public function getMediaFile(): ?File
    {
        return $this->mediaFile;
    }

    public function getMediaFileName(): string
    {
        if (! $this->mediaFile instanceof File) {
            throw new Exception('MediaFile is not setted');
        }

        if ($this->mediaFile instanceof UploadedFile) {
            return $this->mediaFile->getClientOriginalName();
        }

        return $this->mediaFile->getFilename();
    }

    public function getMedia(): string
    {
        return $this->media;
    }

    public function setMedia(?string $media): self
    {
        if (null === $media) {
            return $this;
        }

        if ('' !== $this->media) {
            $this->setMediaBeforeUpdate($this->media);
        }

        $this->media = $media;

        return $this;
    }

    public function getStoreIn(): string
    {
        if ('' === $this->projectDir) {
            throw new Exception('must set project dir before');
        }

        return str_replace('%kernel.project_dir%', $this->projectDir, $this->storeIn);
    }

    public function setStoreIn(string $pathToDir): self
    {
        if ('' === $this->projectDir) {
            throw new Exception('must set project dir before');
        }

        $this->storeIn = rtrim(str_replace($this->projectDir, '%kernel.project_dir%', $pathToDir), '/');

        return $this;
    }

    public function getPath(): string
    {
        if ('' === $this->media) {
            throw new LogicException();
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
     * @return Collection<int, Page>
     */
    public function getMainImagePages(): Collection
    {
        return $this->mainImagePages ?? throw new Exception();
    }

    #[ORM\PreRemove]
    public function removeMainImageFromPages(): void
    {
        if (null !== $this->mainImagePages) {
            foreach ($this->mainImagePages as $page) {
                $page->setMainImage(null);
            }
        }
    }

    /**
     * this is used only for media renaming.
     */
    public function getMediaBeforeUpdate(): string
    {
        return $this->mediaBeforeUpdate;
    }

    /**
     * this is used only for media renaming.
     */
    public function setMediaBeforeUpdate(string $mediaBeforeUpdate): self
    {
        if ('' === $this->mediaBeforeUpdate || '' === $mediaBeforeUpdate) {
            $this->mediaBeforeUpdate = $mediaBeforeUpdate;
        }

        return $this;
    }

    /**************** Slug ***************/
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

        if ('' !== $this->getMedia()) {
            $this->setMedia($this->slug.$this->extractExtension($this->getMedia()));
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
            // throw new Exception('debug... thinking setSlug was only used by Vich ???');
            return $this;
        }

        $filenameSlugified = $this->getMediaFromFilename($filename);
        $extension = $this->extractExtensionFromFile();
        $this->setMedia($filenameSlugified);
        $this->slug = substr($filenameSlugified, 0, \strlen($filenameSlugified) - \strlen($extension));

        return $this;
    }

    public function getMediaFromFilename(string $filename = ''): string
    {
        $filename = '' !== $filename ? $filename : $this->getMediaFileName();
        if ('' === $filename) {
            throw new Exception('debug... '); // dd($this->mediaFile);
        }

        $extension = $this->extractExtensionFromFile();

        $filename = $filename;

        return $this->slugifyPreservingExtension($filename, $extension);
    }

    private function slugify(string $slug): string
    {
        return (new Slugify(['regexp' => '/([^A-Za-z0-9\.]|-)+/']))->slugify($slug);
    }

    protected function slugifyPreservingExtension(string $string, string $extension = ''): string
    {
        $extension = '' === $extension ? $this->extractExtension($string) : $extension;
        $string = str_ends_with($string, $extension) ? substr($string, 0, \strlen($string) - \strlen($extension)) : $string;
        $stringSlugify = $this->slugify($string);

        return $stringSlugify.$extension;
    }

    public function getSlug(): string
    {
        if ('' !== $this->slug) {
            return $this->slug;
        }

        if ('' !== $this->getMedia()) {
            return $this->slug = Filepath::removeExtension($this->getMedia());
        }

        $this->slug = (new Slugify())->slugify($this->getName());

        return $this->slug;
    }
}
