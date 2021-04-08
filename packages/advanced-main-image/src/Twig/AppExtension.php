<?php

namespace Pushword\AdvancedMainImage\Twig;

use Pushword\AdvancedMainImage\PageAdvancedMainImageFormField;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('heroSize', [PageAdvancedMainImageFormField::class, 'formatToRatio']),
            //, ['is_safe' => false, 'needs_environment' => true]),
        ];
    }
}
