<?php

namespace Pushword\Core\Entity;

use Cocur\Slugify\Slugify;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use InvertColor\Color;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Utils\F;
use Pushword\Core\Utils\Filepath;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Yaml;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

trait MediaTrait
{
    use TimestampableTrait;

    /** @var string[] */
    private array $safeClientMimeType = [
        'application/gpx+xml',
        'image/svg+xml',
    ];

    /**
     * @ORM\Column(type="string", length=50)
     */
    protected ?string $mimeType = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $storeIn;

    /**
     * @ORM\Column(type="integer")
     */
    protected int $size;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected ?int $height = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected ?int $width = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected ?string $mainColor = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected ?string $media = null;

    /**
     * NOTE : this is used only for media renaming.
     */
    protected ?string $mediaBeforeUpdate = null;

    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     *
     * @Vich\UploadableField(
     *     mapping="media_media",
     *     fileNameProperty="slug",
     *     mimeType="mimeType",
     *     size="size",
     *     dimensions="dimensions"
     * )
     *
     * @var UploadedFile|File|null
     */
    protected $mediaFile = null;

    /**
     * @ORM\Column(type="string", length=100, unique=true)
     */
    protected string $name = '';

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected ?string $names = null;

    protected string $slug = '';

    /**
     * @ORM\OneToMany(
     *     targetEntity="Pushword\Core\Entity\PageInterface",
     *     mappedBy="mainImage"
     * )
     *
     * @var PageInterface[]|Collection<int, PageInterface>
     */
    protected $mainImagePages;

    protected string $projectDir = '';

    public function setProjectDir(string $projectDir): self
    {
        $this->projectDir = $projectDir;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name.' ';
    }

    /** @psalm-suppress InvalidReturnType */
    protected function getExtension(string $string): string
    {
        return F::preg_replace_str('/.*(\\.[^.\\s]{3,4})$/', '$1', $string);
    }

    public function getSlugForce(): string
    {
        return $this->slug;
    }

    /**
     * Used by MediaAdmin.
     */
    public function setSlugForce(?string $slug): self
    {
        if (\in_array($slug, ['', null], true)) {
            return $this;
        }

        $this->slug = (new Slugify(['regexp' => '/([^A-Za-z0-9\.]|-)+/']))->slugify($slug);

        if (null !== $this->media) {
            $this->setMedia($this->slug.$this->getExtension($this->media));
        }

        return $this;
    }

    private function getExtensionFromMediaFile(): string
    {
        if (null === $this->getMediaFile()) {
            throw new Exception();
        }

        $extension = $this->getMediaFile()->guessExtension(); // From MimeType
        $extension = null === $extension ? '' : '.'.$extension;

        // Todo : when using guessExtension, it's using safe mymetype and returning gpx as txt
        if ('.txt' === $extension && '.gpx' === $this->getExtension($this->getMediaFileName())) {
            $extension = '.gpx';
        }

        return $extension;
    }

    /**
     * Used by VichUploader.
     * Permit to setMedia from filename.
     */
    public function setSlug(?string $filename): self
    {
        if (null === $this->getMediaFile()) {
            //throw new Exception('debug... thinking setSlug was only used by Vich ???');
            return $this;
        }

        $filename ??= $this->getMediaFileName();
        if ('' === $filename) {
            throw new Exception('debug... '); //dd($this->mediaFile);
        }

        $extension = $this->getExtensionFromMediaFile();

        $slugSlugified = $this->slugifyPreservingExtension($filename, $extension);

        if (null === $this->media || $this->getExtension($this->media) != $extension) { //$this->getExtension($slugSlugify)) {
            $this->setMedia($slugSlugified);
            $this->slug = Filepath::removeExtension($slugSlugified);
        }

        return $this;
    }

    protected function slugifyPreservingExtension(string $string, string $extension = ''): string
    {
        $extension = '' === $extension ? $this->getExtension($string) : $extension;
        $stringSlugify = (new Slugify())->slugify(Filepath::removeExtension($string));

        return $stringSlugify.$extension;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $executionContext): void
    {
        if (null !== $this->getMimeType() && null !== $this->mediaFile && $this->mediaFile->getMimeType() != $this->getMimeType()) {
            $executionContext
                ->buildViolation('Attention ! Vous essayez de remplacer un fichier d\'un type ('
                    .$this->getMimeType().') par un fichier d\'une autre type ('.$this->mediaFile->getMimeType().')')
                ->atPath('fileName')
                ->addViolation()
            ;
        }
    }

    public function getSlug(): string
    {
        if ('' !== $this->slug) {
            return $this->slug;
        }

        if (null !== $this->media) {
            return $this->slug = Filepath::removeExtension($this->media);
        }

        $this->slug = (new Slugify())->slugify($this->getName()); //Urlizer::urlize($this->getName());

        return $this->slug;
    }

    public function setMediaFile(?File $file = null): void
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
            throw new Exception('MediaFile is not setted');
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

    public function getName(bool $onlyName = false): string
    {
        if ($onlyName) {
            return $this->name;
        }

        return '' === $this->name && null !== $this->getMediaFile() ? $this->getMediaFileName() : $this->name;
    }

    public function getNameLocalized(?string $getLocalized = null, bool $onlyLocalized = false): string
    {
        $names = $this->getNamesParsed();

        return null !== $getLocalized ?
            (isset($names[$getLocalized]) ? $names[$getLocalized] : ($onlyLocalized ? '' : $this->name))
            : $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function getNamesParsed(): array
    {
        $return = null !== $this->names ? Yaml::parse($this->names) : [];

        if (! \is_array($return)) {
            throw new Exception('Names malformatted');
        }

        return $return;
    }

    /**
     * @return mixed
     */
    public function getNames(bool $yamlParsed = false)
    {
        return $yamlParsed && null !== $this->names ? Yaml::parse($this->names) : $this->names;
    }

    public function setNames(?string $names): self
    {
        $this->names = $names;

        return $this;
    }

    public function getNameByLocale(string $locale): string
    {
        $names = $this->getNamesParsed();

        return $names[$locale] ?? $this->getName();
    }

    public function setName(?string $name): self
    {
        $this->name = (string) $name;

        return $this;
    }

    public function getStoreIn(): ?string
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

        $this->storeIn = str_replace($this->projectDir, '%kernel.project_dir%', $pathToDir);

        return $this;
    }

    public function getPath(): string
    {
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
            && \in_array($uploadedFile->getClientMimeType(), $this->safeClientMimeType, true)) {
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
     * @param array<int>|null $dimensions
     */
    public function setDimensions(?array $dimensions): self
    {
        if (null === $dimensions) {
            return $this;
        }

        if (isset($dimensions[0])) {
            $this->width = $dimensions[0];
        }

        if (isset($dimensions[1])) {
            $this->height = $dimensions[1];
        }

        return $this;
    }

    /**
     * @return array<int>
     */
    public function getDimensions(): ?array
    {
        if (null === $this->height || null === $this->width) {
            return null;
        }

        return [$this->width, $this->height];
    }

    public function getRatio(): ?float
    {
        if (null === $this->height || null === $this->width) {
            return null;
        }

        return $this->height / $this->width;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getMainColor(): ?string
    {
        return $this->mainColor;
    }

    public function getMainColorOpposite(): ?string
    {
        if (null === $this->getMainColor()) {
            return null;
        }

        return Color::fromHex($this->getMainColor())->invert(true);
    }

    public function setMainColor(?string $mainColor): self
    {
        $this->mainColor = $mainColor;

        return $this;
    }

    /**
     * @return PageInterface[]|Collection<int, PageInterface>
     */
    public function getMainImagePages()
    {
        return $this->mainImagePages;
    }

    /**
     * @ORM\PreRemove
     */
    public function removeMainImageFromPages(): void
    {
        foreach ($this->mainImagePages as $page) {
            $page->setMainImage(null);
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
     * @param string $mediaBeforeUpdate NOTE : this is used only for media renaming
     */
    public function setMediaBeforeUpdate(string $mediaBeforeUpdate): self
    {
        $this->mediaBeforeUpdate = $mediaBeforeUpdate;

        return $this;
    }

    public function isImage(): bool
    {
        return str_contains((string) $this->getMimeType(), 'image/')
            && \in_array(strtolower(str_replace('image/', '', (string) $this->getMimeType())), ['jpg', 'jpeg', 'png', 'gif'], true);
    }
}
