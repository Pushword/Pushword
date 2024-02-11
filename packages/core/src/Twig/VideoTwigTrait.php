<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppConfig;

use function Safe\preg_match;

trait VideoTwigTrait
{
    abstract public function getApp(): AppConfig;

    public function renderVideo(string $url, string $image, string $alternativeText = '', bool $forceUrl = false): string
    {
        $template = $this->getApp()->getView('/component/video.html.twig');
        $youtube = $forceUrl ? null : static::getYoutubeVideoUrl($url);

        return trim($this->twig->render($template, [
            'url' => $youtube ?? $url,
            'image' => $image,
            'alt' => $alternativeText,
            'embed_code' => null !== $youtube && ! $forceUrl ? $this->getEmbedCode($url) : null, // @phpstan-ignore-line
        ]));
    }

    protected static function getYoutubeVideoUrl(string $url): string
    {
        if (1 === preg_match('~^(?:https?://)?(?:www[.])?(?:youtube[.]com/watch[?]v=|youtu[.]be/)([^&]{11})~', $url, $m)) {
            return $m[1] ?? throw new \Exception();
        }

        return '';
    }

    public function getEmbedCode(string $embed_code): string
    {
        if (($id = self::getYoutubeVideoUrl($embed_code)) !== '') {
            $template = $this->getApp()->getView('/component/video_youtube_embed.html.twig');

            return $this->twig->render($template, ['id' => $id]);
        }

        return '';
    }
}
