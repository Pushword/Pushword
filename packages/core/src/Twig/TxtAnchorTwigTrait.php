<?php

namespace Pushword\Core\Twig;

use Cocur\Slugify\Slugify;
use Pushword\Core\Component\App\AppConfig;

trait TxtAnchorTwigTrait
{
    private \Twig\Environment $twig;

    abstract public function getApp(): AppConfig;

    public function renderTxtAnchor(string $name): string
    {
        $template = $this->getApp()->getView('/component/txt_anchor.html.twig');

        $slugify = new Slugify();
        $name = $slugify->slugify($name);

        return $this->twig->render($template, ['name' => $name]);
    }
}
