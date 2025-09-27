<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Component\App\AppPool;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final class VideoExtension
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AppPool $apps,
    ) {
    }

    #[AsTwigFunction('video', isSafe: ['html'], needsEnvironment: false)]
    public function renderVideo(
        string $url,
        string $image,
        string $alternativeText = '',
        bool $forceUrl = false,
        string $id = '',
    ): string {
        $template = $this->apps->get()->getView('/component/video.html.twig');
        $youtube = $forceUrl ? null : $this->getYoutubeVideoUrl($url);

        return trim($this->twig->render($template, [
            'id' => $id,
            'url' => $youtube ?? $url,
            'image' => $image,
            'alt' => $alternativeText,
            'embed_code' => null !== $youtube && ! $forceUrl ? $this->getEmbedCode($url) : null, // @phpstan-ignore-line
        ]));
    }

    private function getYoutubeVideoUrl(string $url): string
    {
        if (1 === preg_match('~^(?:https?://)?(?:www[.])?(?:youtube[.]com/watch[?]v=|youtu[.]be/)([^&]{11})~', $url, $m)) {
            return $m[1];
        }

        return '';
    }

    #[AsTwigFunction('url_to_embed')]
    public function getEmbedCode(string $embed_code): string
    {
        if (($id = $this->getYoutubeVideoUrl($embed_code)) !== '') {
            $template = $this->apps->get()->getView('/component/video_youtube_embed.html.twig');

            return $this->twig->render($template, ['id' => $id]);
        }

        return '';
    }
}
