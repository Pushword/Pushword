<?php

namespace Pushword\Core\Controller;

use Twig\Environment as Twig;

trait RenderTrait
{
    private Twig $twig;

    /** @required */
    public function setTwig(Twig $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Returns a rendered view.
     * Use by abstract controller without de deprecation message.
     */
    protected function renderView(string $view, array $parameters = []): string
    {
        return $this->twig->render($view, $parameters);
    }
}
