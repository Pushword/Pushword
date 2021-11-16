<?php

namespace Pushword\AdvancedMainImage\Twig;

use Pushword\AdvancedMainImage\PageAdvancedMainImageFormField;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('heroSize', [PageAdvancedMainImageFormField::class, 'formatToRatio']),
            //, ['is_safe' => false, 'needs_environment' => true]),
        ];
    }
}
