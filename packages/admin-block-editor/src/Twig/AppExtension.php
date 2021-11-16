<?php

namespace Pushword\AdminBlockEditor\Twig;

use PiedWeb\RenderAttributes\AttributesTrait;
use stdClass;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    use AttributesTrait;

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('blockWrapperAttr', [$this, 'blockWrapperAttr'], ['is_safe' => ['html'], 'needs_environment' => false]),
            new TwigFunction('needBlockWrapper', [$this, 'needBlockWrapper'], ['is_safe' => ['html'], 'needs_environment' => false]),
        ];
    }

    /**
     * @param stdClass|array<mixed> $blockData
     * @param array<mixed>          $attributes
     */
    public function blockWrapperAttr($blockData, array $attributes = []): string
    {
        $blockData = (array) $blockData;

        if (isset($blockData['tunes']) && isset($blockData['tunes']['anchor']) && '' !== $blockData['tunes']['anchor']) {
            $attributes['id'] = (isset($attributes['id']) ? $attributes['id'] : '').' '.$blockData['tunes']['anchor'];
        }

        return self::mapAttributes($attributes);
    }

    /**
     * @param stdClass|array<mixed> $blockData
     */
    public function needBlockWrapper($blockData): bool
    {
        return '' !== trim($this->blockWrapperAttr($blockData));
    }
}
