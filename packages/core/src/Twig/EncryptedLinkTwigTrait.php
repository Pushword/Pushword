<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\Router\RouterInterface;
use Pushword\Core\Entity\PageInterface;

trait EncryptedLinkTwigTrait
{
    private RouterInterface $router;

    abstract public function getApp(): AppConfig;

    public function renderEncryptedLink($anchor, $path, $attr = [])
    {
        if ($path instanceof PageInterface) {
            $path = $this->router->generate($path);
        }

        if (\is_string($attr)) {
            $attr = ['class' => $attr];
        }

        $attr = array_merge($attr, ['data-rot' => self::encrypt($path)]);
        $template = $this->getApp()->getView('/component/javascript_link.html.twig');
        $renderedLink = $this->twig->render($template, ['anchor' => $anchor, 'attr' => $attr]);

        return $renderedLink;
    }

    public static function encrypt($path)
    {
        if (0 === strpos($path, 'http://')) {
            $path = '-'.substr($path, 7);
        } elseif (0 === strpos($path, 'https://')) {
            $path = '_'.substr($path, 8);
        } elseif (0 === strpos($path, 'mailto:')) {
            $path = '@'.substr($path, 7);
        }

        return str_rot13($path);
    }
}
