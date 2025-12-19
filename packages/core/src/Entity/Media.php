<?php

namespace Pushword\Core\Entity;

use Cocur\Slugify\Slugify;
use DateTime;
use Deprecated;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use LogicException;
use Pushword\Core\Entity\MediaTrait\ImageTrait;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\Taggable;
use Pushword\Core\Entity\SharedTrait\TagsTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Utils\Filepath;
use Pushword\Core\Utils\SafeMediaMimeType;
use Pushword\Core\Utils\SearchNormalizer;

use function Safe\sha1_file;

use Stringable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Yaml;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * #UniqueEntity({"media"}, message="Media name is ever taken by another media.")
 * #UniqueEntity({"name"}, message="Name is ever taken by another media..").
 */
#[Vich\Uploadable]
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
class Media implements IdInterface, Taggable, Stringable
{
    use CustomPropertiesTrait;

    use IdTrait;

    use ImageTrait;

    use TagsTrait;

    use TimestampableTrait;

    public function __construct()
    {
        $this->mainImagePages = new ArrayCollection();
        $this->updatedAt ??= new DateTime();
        $this->createdAt ??= new DateTime();
    }

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $storeIn = '';

    protected string $projectDir = '';

    #[ORM\Column(name: 'media', type: Types::STRING, length: 255)]
    protected string $fileName = ''; // eg : my-file.jpg

    /**
     * NOTE : this is used only for media renaming.
     */
    protected string $fileNameBeforeUpdate = '';

    /**
     * History of previous file names (JSON array).
     * Used to track filename changes and prevent reuse of old filenames.
     *
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    protected array $fileNameHistory = [];

    #[ORM\Column(type: Types::STRING, length: 50)]
    protected ?string $mimeType = null; // @phpstan-ignore-line

    #[ORM\Column(type: Types::INTEGER)]
    protected int $size = 0;

    /**
     * @var UploadedFile|File|null
     */
    #[Vich\UploadableField(mapping: 'media_media', fileNameProperty: 'slug', size: 'size', mimeType: 'mimeType', dimensions: 'dimensions')]
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

        if (null === $extension || '' === $extension) {
            $extension = $this->extractExtension($this->getMediaFileName());
        } else {
            $extension = '.'.$extension;
        }

        return $this->fixExtension($extension);
    }

    /**
     * Because for some format, the mime type extension is not the best.
     */
    private function fixExtension(string $extension): string
    {
        // Todo : when using guessExtension, it's using safe mymetype and returning gpx as txt

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
            dump(debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));

            throw new Exception('MediaFile is not setted');
        }

        if ($this->mediaFile instanceof UploadedFile) {
            return $this->mediaFile->getClientOriginalName();
        }

        return $this->mediaFile->getFilename();
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): self
    {
        if (null === $fileName) {
            return $this;
        }

        if ('' !== $this->fileName && $this->fileName !== $fileName) {
            $this->setFileNameBeforeUpdate($this->fileName);
            $this->addFileNameToHistory($this->fileName);
        }

        $this->fileName = $fileName;

        return $this;
    }

    public function getStoreIn(): string
    {
        if ('' === $this->projectDir) {
            throw new Exception('must set project dir before');
        }

        return rtrim(str_replace('%kernel.project_dir%', $this->projectDir, $this->storeIn), '/');
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
        if ('' === $this->fileName) {
            throw new LogicException();
        }

        return $this->getStoreIn().'/'.$this->fileName;
    }

    public function getMimeType(): ?string
    {
        return $this->normalizeMimeType($this->mimeType);
    }

    public function setMimeType(?string $mimeType): self
    {
        $uploadedFile = $this->getMediaFile();
        if (
            $uploadedFile instanceof UploadedFile
            && \in_array($uploadedFile->getClientMimeType(), SafeMediaMimeType::get(), true)
        ) {
            $mimeType = $uploadedFile->getClientMimeType();
        }

        $this->mimeType = $this->normalizeMimeType($mimeType);

        return $this;
    }

    private function normalizeMimeType(?string $mimeType): ?string
    {
        if (null === $mimeType) {
            return null;
        }

        // Normalize image/jpg to image/jpeg (standard MIME type)
        if ('image/jpg' === $mimeType) {
            return 'image/jpeg';
        }

        return $mimeType;
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
    public function getFileNameBeforeUpdate(): string
    {
        return $this->fileNameBeforeUpdate;
    }

    /**
     * this is used only for media renaming.
     */
    public function setFileNameBeforeUpdate(string $fileNameBeforeUpdate): self
    {
        if ('' === $this->fileNameBeforeUpdate || '' === $fileNameBeforeUpdate) {
            $this->fileNameBeforeUpdate = $fileNameBeforeUpdate;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getFileNameHistory(): array
    {
        return $this->fileNameHistory;
    }

    /**
     * @param string[] $fileNameHistory
     */
    public function setFileNameHistory(array $fileNameHistory): self
    {
        $this->fileNameHistory = $fileNameHistory;

        return $this;
    }

    /**
     * Add a filename to history (if not already present).
     */
    public function addFileNameToHistory(string $fileName): self
    {
        if ('' !== $fileName && ! \in_array($fileName, $this->fileNameHistory, true)) {
            $this->fileNameHistory[] = $fileName;
        }

        return $this;
    }

    /**
     * Check if a filename is in this media's history.
     */
    public function hasFileNameInHistory(string $fileName): bool
    {
        return \in_array($fileName, $this->fileNameHistory, true);
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
     * Used by the admin interface.
     */
    public function setSlugForce(?string $slug): self
    {
        if ('' === $this->alt && null !== $slug) {
            $this->setAlt($slug);
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

        if ('' !== $this->getFileName()) {
            $this->setFileName($this->slug.$this->extractExtension($this->getFileName()));
        }

        return $this;
    }

    /**
     * Used by VichUploader.
     * Permit to setFileName from filename.
     */
    private function setSlugForNewMedia(string $filename): self
    {
        if (null === $this->getMediaFile()) {
            // throw new Exception('debug... thinking setSlug was only used by Vich ???');
            return $this;
        }

        $filenameSlugified = $this->getMediaFromFilename($filename);
        $extension = $this->extractExtensionFromFile();
        $this->setFileName($filenameSlugified);
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

    private function slugify(string $text): string
    {
        // early return if text has only char from A to Z, a to z, 0 to 9, ., - _
        if (1 === \Safe\preg_match('/^[a-z0-9\-_]+$/', $text)) {
            return $text;
        }

        $slug = str_replace(['®', '™'], ' ', $text);

        $slugifier = new Slugify(['regexp' => '/([^A-Za-z0-9\.]|-)+/']);

        $slug = str_replace(['©', '&copy;', '&#169;', '&#xA9;'], '©', $slug);
        $slug = explode('©', $slug, 2);
        $slug = $slugifier->slugify($slug[0])
            .(isset($slug[1]) ? '_'.$slugifier->slugify(str_replace('©', '', $slug[1])) : '');

        return $slug;
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

        if ('' !== $this->getFileName()) {
            return $this->slug = Filepath::removeExtension($this->getFileName());
        }

        $this->setSlug($this->getAlt());

        return $this->slug;
    }

    #[ORM\Column(name: 'name', type: Types::STRING, length: 100, unique: true)]
    protected string $alt = ''; // used for alt text

    #[ORM\Column(name: 'name_search', type: Types::STRING, length: 100, options: ['default' => ''])]
    protected string $altSearch = '';

    #[ORM\Column(name: 'names', type: Types::TEXT, nullable: true, options: ['default' => ''])]
    protected ?string $alts = '';

    public function __toString(): string
    {
        return $this->fileName;
    }

    private string $softAlt = '';

    public function setAlt(?string $alt, bool $soft = false): self
    {
        if ($soft) {
            $this->softAlt = (string) $alt;

            return $this;
        }

        $this->alt = (string) $alt;
        $this->updateAltSearch();

        return $this;
    }

    private function updateAltSearch(): void
    {
        $altSearch = [$this->alt];
        $altSearch = array_merge($altSearch, array_values($this->getAltsParsed()));
        $altSearch = array_map(SearchNormalizer::normalize(...), $altSearch);
        $altSearch = array_unique($altSearch);
        $this->altSearch = implode(' ', $altSearch);
    }

    public function getAlt(bool $onlyAlt = false): string
    {
        if ('' !== $this->softAlt) {
            return $this->softAlt;
        }

        if ($onlyAlt) {
            return $this->alt;
        }

        if ('' === $this->alt && null !== $this->getMediaFile()) {
            return $this->getMediaFileName();
        }

        return $this->alt;
    }

    public function getAltLocalized(?string $getLocalized = null, bool $onlyLocalized = false): string
    {
        $alts = $this->getAltsParsed();

        return null !== $getLocalized ?
            ($alts[$getLocalized] ?? ($onlyLocalized ? '' : $this->alt))
            : $this->alt;
    }

    /**
     * @return array<string, string>
     */
    public function getAltsParsed(): array
    {
        $this->alts = (string) $this->alts;
        $return = '' !== $this->alts ? Yaml::parse($this->alts) : [];

        if (! \is_array($return)) {
            throw new Exception('Alts malformatted');
        }

        $toReturn = [];

        foreach ($return as $k => $v) {
            if (! \is_string($k) || ! \is_string($v)) {
                throw new Exception();
            }

            $toReturn[$k] = $v;
        }

        return $toReturn;
    }

    public function getAlts(bool $yamlParsed = false): mixed
    {
        $this->alts = (string) $this->alts;

        return $yamlParsed && '' !== $this->alts ? Yaml::parse($this->alts) : $this->alts;
    }

    public function setAlts(?string $alts): self
    {
        $this->alts = (string) $alts;

        return $this;
    }

    public function getAltByLocale(string $locale): string
    {
        $alts = $this->getAltsParsed();

        return $alts[$locale] ?? $this->getAlt();
    }

    /**
     * @var string|resource|null
     */
    #[ORM\Column(type: Types::BINARY, length: 20, options: ['default' => ''])]
    protected $hash;

    public function getHash(): mixed
    {
        return $this->hash ?? $this->setHash()->getHash();
    }

    public function resetHash(): self
    {
        $this->hash = null;

        return $this;
    }

    public function setHash(?string $hash = null): self
    {
        if (null !== $hash) {
            $this->hash = $hash;

            return $this;
        }

        if (($mediaFile = $this->getMediaFile()) !== null && file_exists($mediaFile->getPathname())) {
            $this->hash = sha1_file($mediaFile->getPathname(), true);

            return $this;
        }

        $mediaFilePath = $this->getStoreIn().'/'.$this->getFileName();

        if (! file_exists($mediaFilePath) || ! is_file($mediaFilePath)) {
            throw new Exception('incredible ! `'.$mediaFilePath.'`');
        }

        $this->hash = sha1_file($mediaFilePath, true);

        return $this;
    }

    /**************** Backward Compatibility (Deprecated) ***************/
    #[Deprecated(message: 'Use getFileName() instead', since: '1.0')]
    public function getMedia(): string
    {
        return $this->getFileName();
    }

    #[Deprecated(message: 'Use getAlt() instead', since: '1.0')]
    public function getName(bool $onlyName = false): string
    {
        return $this->getAlt($onlyName);
    }

    public bool $disableRemoveFile = false;
}
