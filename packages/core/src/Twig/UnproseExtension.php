<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppPool;
use Twig\Attribute\AsTwigFilter;
use Twig\Environment as Twig;

final class UnproseExtension
{
    public function __construct(
        private readonly AppPool $apps,
        public Twig $twig,
    ) {
    }

    #[AsTwigFilter('unprose', needsEnvironment: false, isSafe: ['html'])]
    public function unprose(string $html): string
    {
        /** @var Twig */
        $twig = $this->twig;
        $unproseClass = $this->apps->get()->get('unprose') ?? $twig->getGlobals()['unprose'] ?? '';

        if ('' === $unproseClass || ! \is_string($unproseClass)) {
            return $html;
        }

        return '<div class="'.$unproseClass.'">'.$html.'</div>';
    }
}
