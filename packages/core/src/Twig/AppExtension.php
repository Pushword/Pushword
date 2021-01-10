<?php

namespace Pushword\Core\Twig;

use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\RenderAttributes\AttributesTrait;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\Router\RouterInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaExternal;
use Pushword\Core\Entity\PageInterface as Page;
use Pushword\Core\Repository\Repository;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    // TODO switch from Trait to service (will be better to test and add/remove twig extension)
    //use AttributesTrait;
    use EmailTwigTrait;
    use EncryptedLinkTwigTrait;
    use GalleryTwigTrait;
    use PageListTwigTrait;
    use PhoneNumberTwigTrait;
    use TxtAnchorTwigTrait;
    use VideoTwigTrait;

    /** @var RouterInterface */
    protected $router;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var string */
    protected $pageClass;

    /** @var AppPool */
    protected $apps;

    /** @var Twig */
    protected $twig;

    public function __construct(EntityManagerInterface $em, string $pageClass, RouterInterface $router, AppPool $apps, Twig $twig)
    {
        $this->em = $em;
        $this->router = $router;
        $this->pageClass = $pageClass;
        $this->apps = $apps;
        $this->twig = $twig;
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
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('template_from_string', 'addslashes'), // TODO FIX IT
            new TwigFunction('view', [$this, 'getView'], ['needs_environment' => true]),
            new TwigFunction('isCurrentPage', [$this, 'isCurrentPage']), // used ?
            new TwigFunction('isInternalImage', [self::class, 'isInternalImage']), // used ?
            new TwigFunction('getImageFrom', [self::class, 'transformInlineImageToMedia']), // used ?
            // loaded from trait
            new TwigFunction('jslink', [$this, 'renderEncryptedLink'], self::options()),
            new TwigFunction('link', [$this, 'renderEncryptedLink'], self::options()),
            new TwigFunction('encrypt', [self::class, 'encrypt'], self::options()),
            new TwigFunction('mail', [$this, 'renderEncodedMail'], self::options(true)),
            new TwigFunction('email', [$this, 'renderEncodedMail'], self::options(true)),
            new TwigFunction('tel', [$this, 'renderPhoneNumber'], self::options()),
            new TwigFunction('bookmark', [$this, 'renderTxtAnchor'], self::options()),
            new TwigFunction('anchor', [$this, 'renderTxtAnchor'], self::options()),
            new TwigFunction('gallery', [$this, 'renderGallery'], self::options()),
            new TwigFunction('video', [$this, 'renderVideo'], self::options()),
            new TwigFunction('embedCode', [$this, 'getEmbedCode']),
            new TwigFunction('list', [$this, 'renderPagesList'], self::options(true)),
            new TwigFunction('card_list', [$this, 'renderPagesListCard'], self::options(true)),
            new TwigFunction('children', [$this, 'renderChildrenList'], self::options(true)),
            new TwigFunction('card_children', [$this, 'renderChildrenListCard'], self::options(true)),
            new TwigFunction('getPages', [$this, 'getPublishedPages'], self::options(true)),
            new TwigFunction('str_rot13', [$this, 'rot13'], self::options()),
        ];
    }

    public function rot13(string $str): string
    {
        return str_rot13($str);
    }

    public function getPublishedPages(Twig $twig, $host = null, $where = [], $orderBy = [], $limit = 0)
    {
        return Repository::getPageRepository($this->em, $this->pageClass)->getPublishedPages($host, $where, $orderBy, $limit);
    }

    public function getView(Twig $twig, $path)
    {
        return $this->apps->get()->getView($path, $twig);
    }

    public static function options($needsEnv = false, $isSafe = ['html'])
    {
        return ['is_safe' => $isSafe, 'needs_environment' => $needsEnv];
    }

    public static function isInternalImage(string $media): bool
    {
        return 0 === strpos($media, '/media/default/');
    }

    public static function transformInlineImageToMedia(string $src)
    {
        return self::isInternalImage($src) ? Media::loadFromSrc($src) : MediaExternal::load($src);
    }

    public function isCurrentPage(string $uri, ?Page $currentPage)
    {
        return
            null === $currentPage || $uri != $this->router->generate($currentPage->getRealSlug())
            ? false
            : true;
    }

    public static function pregReplace($subject, $pattern, $replacement)
    {
        return preg_replace($pattern, $replacement, $subject);
    }
}
