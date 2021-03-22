<?php

namespace Pushword\Core\Twig;

use Exception;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\Router\RouterInterface;
use Pushword\Core\Entity\PageInterface;

trait LinkTwigTrait
{
    private RouterInterface $router;

    abstract public function getApp(): AppConfig;

    public function renderLink($anchor, $path, $attr = [], bool $encrypt = true)
    {
        if (\is_bool($attr)) {
            $encrypt = $attr;
            $attr = [];
        }

        if (\is_array($path)) {
            $attr = $path;
            if (! isset($attr['href'])) {
                throw new Exception('attr must contain href for render a link.');
            }
            $path = $attr['href'];
        }

        if (\is_string($attr)) {
            $attr = ['class' => $attr];
        }

        if ($path instanceof PageInterface) {
            $path = $this->router->generate($path);
        }

        if ($encrypt) {
            $attr = array_merge($attr, ['data-rot' => self::encrypt($path)]);
            $template = $this->getApp()->getView('/component/link_js.html.twig');
            $renderedLink = $this->twig->render($template, ['anchor' => $anchor, 'attr' => $attr]);
        } else {
            $attr = array_merge($attr, ['href' => $path]);
            $template = $this->getApp()->getView('/component/link.html.twig');
            $renderedLink = $this->twig->render($template, ['anchor' => $anchor, 'attr' => $attr]);
        }

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
