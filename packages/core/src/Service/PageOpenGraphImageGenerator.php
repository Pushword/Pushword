<?php

namespace Pushword\Core\Service;

use Imagine\Draw\DrawerInterface;
use Imagine\Gd\Font;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment as Twig;

/**
 * Credit JoliCode
 * https://jolicode.com/blog/create-your-own-shiny-open-graph-images-with-imagine-php.
 */
class PageOpenGraphImageGenerator
{
    private ?RGB $rgb = null;

    public ?ImagineInterface $imagine = null;

    public PageInterface $page;

    public function __construct(
        private readonly AppPool $apps,
        private readonly Twig $twig,
        private readonly Filesystem $filesystem,
        private readonly string $publicDir,
        private readonly string $publicMediaDir,
        private readonly int $imageHeight = 600,
        private readonly int $imageWidth = 1200,
        private readonly int $marginSize = 40,
    ) {
        if (null !== $this->apps->getCurrentPage()) {
            $this->page = $this->apps->getCurrentPage();
        }
    }

    public function getPath(bool $browserPath = false): string
    {
        return ($browserPath ? '' : $this->publicDir).'/'.$this->publicMediaDir.'/og/'
            .str_replace('/', '_', $this->page->getSlug()).'-'
            .substr(sha1($this->page->getId().$this->apps->get()->getHosts()[0]), 0, 6).'.png';
    }

    public function generatePreviewImage(): void
    {
        $image = $this->getImagine()->create(
            new Box($this->imageWidth, $this->imageHeight),
        );

        $drawer = $image->draw();
        $this->drawTitle($drawer);
        $this->drawAuthorName($drawer);
        $this->drawLogo($image);
        $this->drawFooter($drawer);

        $this->filesystem->mkdir($this->publicDir.'/'.$this->publicMediaDir.'/og/');

        $image->save($this->getPath());
    }

    private function drawTitle(DrawerInterface $drawer): void
    {
        $titleText = $this->page->getH1() ?? '...';

        if (\strlen($titleText) > 90) {
            $titleText = substr($titleText, 0, 87).'…';
        }

        $drawer->text(
            $titleText,
            $this->getFont('regular', 40),
            new Point($this->marginSize, 150),
            0,
            $this->imageWidth - $this->marginSize * 2
        );
    }

    private function drawAuthorName(DrawerInterface $drawer): void
    {
        $author = $this->apps->get()->getHosts()[0];
        // $this->page->getCustomProperty('Author') ?? ' ';

        $drawer->text(
            $author,
            $this->getFont('light', 30),
            new Point($this->marginSize, 100),
        );
    }

    private function drawFooter(DrawerInterface $drawer): void
    {
        $leftTop = new Point(0, $this->imageHeight - 10);
        $rightBottom = new Point($this->imageWidth, $this->imageHeight);

        $drawer->rectangle(
            $leftTop,
            $rightBottom,
            $this->getRgb()->color($this->apps->get()->getStr('css_var:color_primary', '#EF8206')), // replace per primary
            true,
        );
    }

    private function drawLogo(ImageInterface $image): void
    {
        $logo = $this->apps->get()->getView('/page/OpenGrapImageGenerator/logo.png');
        $logo = $this->getImagine()->open($this->twig->getLoader()->getSourceContext($logo)->getPath());
        $logoSize = $logo->getSize();
        $bottomRight = new \Imagine\Image\Point(
            $this->imageWidth - $logoSize->getWidth() - $this->marginSize,
            $this->imageHeight - $logoSize->getHeight() - $this->marginSize
        );
        $image->paste($logo, $bottomRight);
    }

    private function getFont(string $fontType = 'bold', int $size = 37, ?ColorInterface $color = null): Font
    {
        $font = $this->apps->get()->getView('@Pushword/page/OpenGrapImageGenerator/'.$fontType.'.ttf');

        return new Font(
            $this->twig->getLoader()->getSourceContext($font)->getPath(),
            $size,
            $color ?? $this->getRgb()->color('#0f172a')
        );
    }

    private function getImagine(): ImagineInterface
    {
        return $this->imagine ?? new \Imagine\Imagick\Imagine();
    }

    private function getRgb(): RGB
    {
        if (null === $this->rgb) {
            return $this->rgb = new RGB();
        }

        return $this->rgb;
    }

    public function setPage(PageInterface $page): static
    {
        $this->page = $page;

        return $this;
    }
}
