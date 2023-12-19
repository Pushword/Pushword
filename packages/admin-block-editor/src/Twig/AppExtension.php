<?php

namespace Pushword\AdminBlockEditor\Twig;

use Pushword\Core\Component\App\AppPool;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly AppPool $appPool,
        private readonly \Pushword\Core\Router\PushwordRouteGenerator $router
    ) {
    }

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('blockWrapperAttr', $this->blockWrapperAttr(...), ['is_safe' => ['html'], 'needs_environment' => false]),
            new TwigFunction('needBlockWrapper', $this->needBlockWrapper(...), ['is_safe' => ['html'], 'needs_environment' => false]),
        ];
    }

    /**
     * @return \Twig\TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('fixHref', $this->fixHref(...), ['is_safe' => ['html'], 'needs_environment' => false]),
        ];
    }

    /**
     * @param array<mixed>|\stdClass $blockData
     * @param array<mixed>           $attributes
     */
    public function blockWrapperAttr(array|\stdClass $blockData, array $attributes = []): string
    {
        $blockData = (array) \Safe\json_decode(\Safe\json_encode($blockData), true);

        if (! isset($blockData['tunes']) || ! \is_array($blockData['tunes'])) {
            return \PiedWeb\RenderAttributes\Attribute::renderAll($attributes);
        }

        if (isset($blockData['tunes']['anchor']) && '' !== $blockData['tunes']['anchor']) {
            $attributes['id'] = trim(($attributes['id'] ?? '').' '.$blockData['tunes']['anchor']);
        }

        if (isset($blockData['tunes']['class']) && '' !== $blockData['tunes']['class']) {
            $attributes['class'] = trim(($attributes['class'] ?? '').' '.$blockData['tunes']['class']);
        }

        $alignment = $blockData['tunes']['textAlign']['alignment'] ?? $blockData['data']['alignment'] ?? '';

        if ('center' === $alignment) {
            $attributes['class'] = trim(($attributes['class'] ?? '').' text-center');
        } elseif ('right' === $alignment) {
            $attributes['class'] = trim(($attributes['class'] ?? '').' text-right');
        } elseif ('justify' === $alignment) {
            $attributes['class'] = trim(($attributes['class'] ?? '').' text-justify');
        }

        return \PiedWeb\RenderAttributes\Attribute::renderAll($attributes);
    }

    /**
     * @param array<mixed>|\stdClass $blockData
     */
    public function needBlockWrapper(array|\stdClass $blockData): bool
    {
        return '' !== trim($this->blockWrapperAttr($blockData));
    }

    public function fixHref(string $text): string
    {
        $regex = '/"(https?)?:\/\/([a-zA-Z0-9.-:]+)\/'.$this->getHostsRegex().'\/?([^"]*)"/';

        preg_match_all($regex, $text, $matches);
        $counter = \count($matches[0]);
        for ($i = 0; $i < $counter; ++$i) {
            $text = str_replace($matches[0][$i], '"'.$this->router->generate($matches[4][$i] ?? 'homepage', host: $matches[3][$i]).'"', $text);
        }

        return $text;
    }

    private ?string $hostRegex = null;

    private function getHostsRegex(): string
    {
        return $this->hostRegex ??= '('.implode('|', array_map('preg_quote', $this->appPool->getHosts())).')';
    }
}
