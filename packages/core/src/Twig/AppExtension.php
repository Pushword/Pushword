<?php

namespace Pushword\Core\Twig;

use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PiedWeb\RenderAttributes\AttributesTrait;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\ManagerPoolInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaInterface;
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

class AppExtension extends AbstractExtension
{
    // TODO switch from Trait to service (will be better to test and add/remove twig extension)
    //use AttributesTrait;
    use ClassTrait;
    use GalleryTwigTrait;
    use LinkTwigTrait;
    use PageListTwigTrait;
    use PhoneNumberTwigTrait;
    use TxtAnchorTwigTrait;
    use UnproseTwigTrait;
    use VideoTwigTrait;

    private RouterInterface $router;

    private EntityManagerInterface $em;

    private string $pageClass;

    private AppPool $apps;

    private Twig $twig;

    private ImageManager $imageManager;

    private ManagerPoolInterface $entityFilterManagerPool;

    public function __construct(EntityManagerInterface $em, string $pageClass, RouterInterface $router, AppPool $apps, Twig $twig, ImageManager $imageManager, ManagerPoolInterface $entityFilterManagerPool)
    {
        $this->em = $em;
        $this->router = $router;
        $this->pageClass = $pageClass;
        $this->apps = $apps;
        $this->twig = $twig;
        $this->imageManager = $imageManager;
        $this->entityFilterManagerPool = $entityFilterManagerPool;
    }

    public function getApp(): AppConfig
    {
        return $this->apps->get();
    }

    public function getFilters()
    {
        return [
            new TwigFilter('html_entity_decode', 'html_entity_decode'),
            new TwigFilter('slugify', [(new Slugify()), 'slugify']),
            new TwigFilter('preg_replace', [self::class, 'pregReplace']),
            new TwigFilter('nice_punctuation', [HtmlBeautifer::class, 'punctuationBeautifer'], self::options()),
            new TwigFilter('unprose', [$this, 'unprose'], self::options()),
            new TwigFilter('image', [$this->imageManager, 'getBrowserPath'], self::options()),
            new TwigFilter('markdown', [(new MarkdownParser()), 'transform'], self::options()),
        ];
    }

    public function getFunctions()
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
            new TwigFunction('class', [$this, 'getHtmlClass'], self::options()),
            new TwigFunction('pw', [$this->entityFilterManagerPool, 'getProperty'], self::options()),
            new TwigFunction('filesize', [FilesizeFormatter::class, 'formatBytes'], self::options()),
        ];
    }

    public function getPublishedPages($host = null, $where = [], $orderBy = [], $limit = 0, $withRedirection = false)
    {
        return Repository::getPageRepository($this->em, $this->pageClass)
            ->getPublishedPages($host, $where, $orderBy, $limit, (bool) $withRedirection);
    }

    public function getView(string $path, ?string $fallback = null)
    {
        return $fallback ? $this->apps->get()->getView($path, $fallback)
            : $this->apps->get()->getView($path);
    }

    public static function options($needsEnv = false, $isSafe = ['html'])
    {
        return ['is_safe' => $isSafe, 'needs_environment' => $needsEnv];
    }

    public static function isInternalImage(string $media): bool
    {
        return 0 === strpos($media, '/media/default/') || false === strpos($media, '/');
    }

    public static function normalizeMediaPath(string $src): string
    {
        if (preg_match('/^[a-z-]$/', $src)) {
            return '/media/default/'.$src.'.jpg';
        }

        return $src;
    }

    /**
     * Convert the source path (often /media/default/... or just ...) in a Media Object
     * /!\ No search in db.
     *
     * @param string|MediaInterface $src
     */
    public function transformStringToMedia($src, string $name = ''): MediaInterface
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

    public static function pregReplace($subject, $pattern, $replacement)
    {
        return preg_replace($pattern, $replacement, $subject);
    }
}
