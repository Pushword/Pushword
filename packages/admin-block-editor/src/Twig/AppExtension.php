<?php

namespace Pushword\AdminBlockEditor\Twig;

use PiedWeb\RenderAttributes\Attribute;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Router\PushwordRouteGenerator;

use function Safe\json_decode;
use function Safe\json_encode;

use stdClass;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

class AppExtension
{
    public function __construct(
        private readonly AppPool $appPool,
        private readonly PushwordRouteGenerator $router
    ) {
    }

    #[AsTwigFunction('blockWrapperId', isSafe: ['html'], needsEnvironment: false)]
    public function blockWrapperId(string $id, bool $returnId = false): string
    {
        if ('' === $id) {
            return '';
        }

        if (str_contains($id, '"')) {
            throw new \Exception('Block wrapper id cannot contain quotes');
        }

        if (str_contains($id, ' ')) {
            throw new \Exception('Block wrapper id cannot contain spaces');
        }

        return $returnId ? ' id="'.$id.'"' : '#'.$id;
    }

    #[AsTwigFunction('blockWrapperClass', isSafe: ['html'], needsEnvironment: false)]
    public function blockWrapperClass(string $class): string
    {
        if ('' === $class) {
            return '';
        }

        return ' class="'.str_replace('"', '&quot;', $class).'"';
    }

    #[AsTwigFunction('blockWrapperAlignment', isSafe: ['html'], needsEnvironment: false)]
    public function blockWrapperAlignment(string $alignment, bool $returnClass = false): string
    {
        if ('' === $alignment) {
            return '';
        }

        // text-right text-left text-center text-justify
        $class = 'text-'.$alignment;

        return $returnClass ? ' class="'.$class.'"' : '.'.$class;
    }

    /**
     * @param array<mixed> $attributes
     */
    private function renderAttributesForMarkdown(array $attributes): string
    {
        if ([] === $attributes) {
            return '';
        }

        $attr = [];
        foreach ($attributes as $k => $v) {
            if ('class' === $k && is_scalar($v) && '' !== $v) {
                $attr[] = '.'.str_replace(' ', '.', (string) $v);
            } elseif ('id' === $k && is_scalar($v) && '' !== $v) {
                $attr[] = '#'.(string) $v;
            } elseif (is_scalar($v) && '' !== $v) {
                $attr[] = $k.'="'.str_replace('"', '\"', (string) $v).'"';
            }
        }

        return [] === $attr ? '' : ' { '.implode(' ', $attr).' }';
    }

    /**
     * @param array<mixed>|stdClass $blockData
     * @param array<mixed>          $attributes
     */
    #[AsTwigFunction('blockWrapperAttr', isSafe: ['html'], needsEnvironment: false)]
    public function blockWrapperAttr(array|stdClass $blockData, array $attributes = [], bool $forMarkdown = false): string
    {
        $blockData = (array) json_decode(json_encode($blockData), true);

        if (! isset($blockData['tunes']) || ! \is_array($blockData['tunes'])) {
            return $forMarkdown ? $this->renderAttributesForMarkdown($attributes)
                : Attribute::renderAll($attributes);
        }

        if (isset($blockData['tunes']['anchor']) && is_string($blockData['tunes']['anchor']) && '' !== $blockData['tunes']['anchor']) {
            $attributes['id'] = $blockData['tunes']['anchor'];
        } else {
            $attributes['id'] = isset($attributes['id']) && is_string($attributes['id']) ? $attributes['id'] : '';
        }

        if ('' === $attributes['id']) {
            unset($attributes['id']);
        }

        $attributes['class'] = isset($attributes['class']) && is_string($attributes['class']) ? trim($attributes['class']) : '';
        if (isset($blockData['tunes']['class']) && is_string($blockData['tunes']['class'])) {
            $attributes['class'] .= ' '.$blockData['tunes']['class'];
        }

        $alignment = $blockData['tunes']['textAlign']['alignment'] ?? $blockData['data']['alignment'] ?? ''; // @phpstan-ignore-line

        if ('center' === $alignment) {
            $attributes['class'] .= ' text-center';
        } elseif ('right' === $alignment) {
            $attributes['class'] .= ' text-right';
        } elseif ('justify' === $alignment) {
            $attributes['class'] .= ' text-justify';
        }

        $attributes['class'] = trim($attributes['class']);

        return $forMarkdown ? $this->renderAttributesForMarkdown($attributes)
            : Attribute::renderAll($attributes);
    }

    /**
     * @param array<mixed>|stdClass $blockData
     */
    #[AsTwigFunction('needBlockWrapper', isSafe: ['html'], needsEnvironment: false)]
    public function needBlockWrapper(array|stdClass $blockData): bool
    {
        return '' !== trim($this->blockWrapperAttr($blockData));
    }

    #[AsTwigFilter('fixHref', isSafe: ['html'], needsEnvironment: false)]
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

    /**
     * Extract media name from legacy image data formats
     * Supports: {media: "name"}, {file: {url: "url"}}, {file: {media: "name"}}, {file: "url"}, "url".
     *
     * @param array<mixed, mixed>|stdClass|string $data
     */
    #[AsTwigFunction('legacyImageName', needsEnvironment: false)]
    public function legacyImageName(array|stdClass|string $data): string
    {
        // If it's a string, it might be a URL or a media name
        if (is_string($data)) {
            return $this->extractMediaNameFromUrl($data);
        }

        // Convert stdClass to array for easier processing
        if ($data instanceof stdClass) {
            $data = (array) $data;
        }

        // New format: {media: "filename.jpg"}
        if (isset($data['media']) && is_string($data['media'])) {
            return $this->extractMediaNameFromUrl($data['media']);
        }

        // Legacy format: {file: ...}
        if (isset($data['file'])) {
            $file = $data['file'];

            if ($file instanceof stdClass) {
                $file = (array) $file;
            }

            // {file: {media: "filename.jpg"}}
            if (is_array($file) && isset($file['media']) && is_string($file['media'])) {
                return $this->extractMediaNameFromUrl($file['media']);
            }

            // {file: {url: "/media/md/filename.jpg"}}
            if (is_array($file) && isset($file['url']) && is_string($file['url'])) {
                return $this->extractMediaNameFromUrl($file['url']);
            }

            // {file: "url_or_name"}
            if (is_string($file)) {
                return $this->extractMediaNameFromUrl($file);
            }

            // {file: {}} - complex object, try to find url property
            if (is_array($file)) {
                foreach ($file as $key => $value) {
                    if ('url' === $key && is_string($value)) {
                        return $this->extractMediaNameFromUrl($value);
                    }
                }
            }
        }

        // Legacy format: {url: "/media/md/filename.jpg"}
        if (isset($data['url']) && is_string($data['url'])) {
            return $this->extractMediaNameFromUrl($data['url']);
        }

        return '';
    }

    /**
     * Convert array of legacy image data to array of media names.
     *
     * @param array<mixed> $images
     *
     * @return array<string>
     */
    #[AsTwigFunction('legacyImageArray', needsEnvironment: false)]
    public function legacyImageArray(array $images): array
    {
        $processedImages = [];

        foreach ($images as $image) {
            // Ensure we pass the correct types to legacyImageName
            if (is_array($image) || $image instanceof stdClass || is_string($image)) {
                $mediaName = $this->legacyImageName($image);
                if ('' !== $mediaName) {
                    $processedImages[] = $mediaName;
                }
            }
        }

        return $processedImages;
    }

    /**
     * Extract media name from URL or return as-is if it's already a media name.
     */
    private function extractMediaNameFromUrl(string $urlOrName): string
    {
        if ('' === $urlOrName) {
            return '';
        }

        // If it starts with http or /, it's a URL - extract the filename
        if (str_starts_with($urlOrName, 'http') || str_starts_with($urlOrName, '/')) {
            $parts = explode('/', $urlOrName);

            return end($parts) ?: '';
        }

        // Otherwise, it's already a media name
        return $urlOrName;
    }
}
