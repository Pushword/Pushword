<?php

namespace Pushword\Core\Entity;

use Cocur\Slugify\Slugify;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use InvertColor\Color;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
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

    /**
     * @ORM\Column(type="string", length=50)
     */
    protected $mimeType;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $storeIn;

    /**
     * @ORM\Column(type="integer")
     */
    protected $size;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $height;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $width;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $mainColor;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $media;

    /**
     * NOTE : this is used only for media renaming.
     *
     * @var string
     */
    protected $mediaBeforeUpdate;

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
     * @var UploadedFile|File
     */
    protected $mediaFile;

    /**
     * @ORM\Column(type="string", length=100, unique=true)
     */
    protected string $name = '';

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $names;

    protected $slug;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Pushword\Core\Entity\PageInterface",
     *     mappedBy="mainImage"
     * )
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

    protected function getExtension($string)
    {
        return preg_replace('/.*(\\.[^.\\s]{3,4})$/', '$1', $string);
    }

    protected function slugifyPreservingExtension($string)
    {
        $extension = $this->getExtension($string);
        $stringSlugify = (new Slugify())->slugify(Filepath::removeExtension($string));

        return $stringSlugify.$extension;
    }

    public function getSlugForce()
    {
        return $this->slug;
    }

    /**
     * Used by MediaAdmin.
     */
    public function setSlugForce($slug)
    {
        if (! $slug) {
            return $this;
        }

        $this->slug = (new Slugify(['regexp' => '/([^A-Za-z0-9\.]|-)+/']))->slugify($slug);

        if ($this->media) {
            $this->setMedia($this->slug.$this->getExtension($this->media));
        }

        return $this;
    }

    /**
     * Used by VichUploader.
     */
    public function setSlug($slug): self
    {
        if (! $slug) {
            return $this;
        }

        $slugSlugify = $this->slugifyPreservingExtension($slug);

        if ($this->getExtension($this->media) != $this->getExtension($slugSlugify)) {
            $this->setMedia($slugSlugify);
        }

        return $this;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        if ($this->getMimeType() && $this->mediaFile && $this->mediaFile->getMimeType() != $this->getMimeType()) {
            $context
                ->buildViolation('Attention ! Vous essayez de remplacer un fichier d\'un type ('
                    .$this->getMimeType().') par un fichier d\'une autre type ('.$this->mediaFile->getMimeType().')')
                ->atPath('fileName')
                ->addViolation()
            ;
        }
    }

    public function getSlug(): string
    {
        if ($this->slug) {
            return $this->slug;
        }

        if ($this->media) {
            return $this->slug = Filepath::removeExtension($this->media);
        }

        $this->slug = (new Slugify())->slugify($this->getName()); //Urlizer::urlize($this->getName());

        return $this->slug;
    }

    public function setMediaFile(?File $media = null): void
    {
        $this->mediaFile = $media;

        if (null !== $media) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getMediaFile(): ?File
    {
        return $this->mediaFile;
    }

    public function getMedia(): ?string
    {
        return $this->media;
    }

    public function setMedia($media): self
    {
        if (! $media) {
            return $this;
        }

        if (null !== $this->media) {
            $this->setMediaBeforeUpdate($this->media);
        }

        $this->media = $media;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNameLocalized($getLocalized = null, $onlyLocalized = false): string
    {
        $names = $this->getNames(true);

        return $getLocalized ?
            (isset($names[$getLocalized]) ? $names[$getLocalized] : ($onlyLocalized ? '' : $this->name))
            : $this->name;
    }

    public function getNames($yaml = false)
    {
        return true === $yaml && null !== $this->names ? Yaml::parse($this->names) : $this->names;
    }

    public function setNames(?string $names): self
    {
        $this->names = $names;

        return $this;
    }

    public function getNameByLocale($locale)
    {
        $names = $this->getNames(true);

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

    public function setMimeType($mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setSize($size): self
    {
        $this->size = $size;

        return $this;
    }

    public function setDimensions($dimensions): self
    {
        if (isset($dimensions[0])) {
            $this->width = (int) $dimensions[0];
        }

        if (isset($dimensions[1])) {
            $this->height = (int) $dimensions[1];
        }

        return $this;
    }

    public function getDimensions(): array
    {
        return [$this->width, $this->height];
    }

    public function getRatio(): float
    {
        return $this->height / $this->width;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getMainColor(): ?string
    {
        return $this->mainColor;
    }

    public function getMainColorOpposite()
    {
        if (null === $this->getMainColor()) {
            return;
        }

        return Color::fromHex($this->getMainColor())->invert(true);
    }

    public function setMainColor(?string $mainColor): self
    {
        $this->mainColor = $mainColor;

        return $this;
    }

    public function getMainImagePages()
    {
        return $this->mainImagePages;
    }

    /**
     * @ORM\PreRemove
     */
    public function removeMainImageFromPages()
    {
        foreach ($this->mainImagePages as $page) {
            $page->setMainImage(null);
        }
    }

    /**
     * this is used only for media renaming.
     *
     * @return string
     */
    public function getMediaBeforeUpdate()
    {
        return $this->mediaBeforeUpdate;
    }

    /**
     * this is used only for media renaming.
     *
     * @param string $mediaBeforeUpdate NOTE : this is used only for media renaming
     *
     * @return self
     */
    public function setMediaBeforeUpdate(string $mediaBeforeUpdate)
    {
        $this->mediaBeforeUpdate = $mediaBeforeUpdate;

        return $this;
    }
}
