<?php

namespace Pushword\Core\Twig;

use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\RenderAttributes\AttributesTrait;
use Pushword\Core\AutowiringTrait\RequiredPageClass;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\ManagerPoolInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Router\RouterInterface;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Utils\FilesizeFormatter;
use Pushword\Core\Utils\HtmlBeautifer;
use Pushword\Core\Utils\MarkdownParser;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @template T of object
 */
class AppExtension extends AbstractExtension
{
    // TODO switch from Trait to service (will be better to test and add/remove twig extension)
    // use AttributesTrait;
    use GalleryTwigTrait;
    use LinkTwigTrait;
    use PageListTwigTrait;
    use PhoneNumberTwigTrait;
    use RequiredPageClass;
    use TxtAnchorTwigTrait;
    use UnproseTwigTrait;
    use VideoTwigTrait;

    /**
     * @param ManagerPoolInterface<T> $entityFilterManagerPool
     */
    public function __construct(private EntityManagerInterface $em, private RouterInterface $router, private AppPool $apps, private Twig $twig, private ImageManager $imageManager, private ManagerPoolInterface $entityFilterManagerPool)
    {
    }

    public function getApp(): AppConfig
    {
        return $this->apps->get();
    }

    /**
     * @return \Twig\TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('md5', 'md5'),
            new TwigFilter('html_entity_decode', 'html_entity_decode'),
            new TwigFilter('slugify', [(new Slugify()), 'slugify']),
            new TwigFilter('preg_replace', [self::class, 'pregReplace']),
            new TwigFilter('nice_punctuation', [HtmlBeautifer::class, 'punctuationBeautifer'], self::options()),
            new TwigFilter('unprose', [$this, 'unprose'], self::options()),
            new TwigFilter('image', [$this->imageManager, 'getBrowserPath'], self::options()),
            new TwigFilter('markdown', [(new MarkdownParser()), 'transform'], self::options()),
            new TwigFilter('view', [$this, 'getView'], ['needs_environment' => false]),
        ];
    }

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('view', [$this, 'getView'], ['needs_environment' => false]),
            new TwigFunction('is_internal_image', [self::class, 'isInternalImage']), // used ?
            new TwigFunction('media_from_string', [$this, 'transformStringToMedia']), // used ?
            // loaded from trait
            new TwigFunction('jslink', [$this, 'renderLink'], self::options()),
            new TwigFunction('link', [$this, 'renderLink'], self::options()),
            new TwigFunction('encrypt', [self::class, 'encrypt'], self::options()),
            new TwigFunction('mail', [$this, 'renderEncodedMail'], self::options()),
            new TwigFunction('email', [$this, 'renderEncodedMail'], self::options()),
            new TwigFunction('tel', [$this, 'renderPhoneNumber'], self::options()),
            new TwigFunction('bookmark', [$this, 'renderTxtAnchor'], self::options()),
            new TwigFunction('anchor', [$this, 'renderTxtAnchor'], self::options()),
            new TwigFunction('gallery', [$this, 'renderGallery'], self::options()),
            new TwigFunction('video', [$this, 'renderVideo'], self::options()),
            new TwigFunction('url_to_embed', [$this, 'getEmbedCode']),
            new TwigFunction('pager', [$this, 'renderPager'], self::options(true)),
            new TwigFunction('pages_list', [$this, 'renderPagesList'], self::options(true)),
            new TwigFunction('pages', [$this, 'getPublishedPages'], self::options()),
            new TwigFunction('pw', [$this->entityFilterManagerPool, 'getProperty'], self::options()),
            new TwigFunction('filesize', [FilesizeFormatter::class, 'formatBytes'], self::options()),
            new TwigFunction('page_position', [$this, 'getPagePosition'], self::options()),
            new TwigFunction('image_dimensions', [$this->imageManager, 'getDimensions'], self::options()),
            new TwigFunction('class_exists', 'class_exists'),
        ];
    }

    public function getPagePosition(PageInterface $page): int
    {
        if (null !== $page->getParentPage()) {
            return $this->getPagePosition($page->getParentPage()) + 1;
        }

        return 1;
    }

    /**
     * @param string|string[]|null $host
     * @param array<(string|int), string> $orderBy
     * @param array<mixed> $where
     * @param int|array<(string|int), int> $limit
     *
     * @return PageInterface[]
     */
    public function getPublishedPages($host = null, array $where = [], array $orderBy = [], array|int $limit = 0, bool $withRedirection = false): array
    {
        return Repository::getPageRepository($this->em, $this->getPageClass())
            ->getPublishedPages(null === $host ? [] : $host, $where, $orderBy, $limit, $withRedirection);
    }

    public function getView(string $path, ?string $fallback = null): string
    {
        return null !== $fallback ? $this->apps->get()->getView($path, $fallback)
            : $this->apps->get()->getView($path);
    }

    /**
     * @param array<string> $isSafe
     *
     * @return array<string, mixed>
     */
    public static function options(bool $needsEnv = false, array $isSafe = ['html']): array
    {
        return ['is_safe' => $isSafe, 'needs_environment' => $needsEnv];
    }

    public static function isInternalImage(string $media): bool
    {
        return str_starts_with($media, '/media/default/') || ! str_contains($media, '/');
    }

    public static function normalizeMediaPath(string $src): string
    {
        if (1 === \Safe\preg_match('/^[a-z-]+$/', $src)) {
            return '/media/default/'.$src.'.jpg';
        }

        return $src;
    }

    /**
     * Convert the source path (often /media/default/... or just ...) in a Media Object
     * /!\ No search in db.
     */
    public function transformStringToMedia(MediaInterface|string $src, string $name = ''): MediaInterface
    {
        $src = \is_string($src) ? self::normalizeMediaPath($src) : $src;

        if (\is_string($src) && self::isInternalImage($src)) {
            $media = Media::loadFromSrc($src);
            $media->setName($name);

            return $media;
        }

        if (\is_string($src) && false !== $this->imageManager->cacheExternalImage($src)) {
            return $this->imageManager->importExternal($src, $name);
        }

        if (! $src instanceof MediaInterface) {
            throw new Exception('Can\'t handle the value submitted ('.$src.')');
        }

        return $src;
    }

    /**
     * @param string[]|string $subject
     * @param string[]|string $pattern
     * @param string[]|string $replacement
     *
     * @return string[]|string
     */
    public static function pregReplace(array|string $subject, array|string $pattern, array|string $replacement): array|string
    {
        return \Safe\preg_replace($pattern, $replacement, $subject);
    }
}
