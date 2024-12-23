<?php

namespace Pushword\Core\Twig;

use Override;
use Pushword\Core\Component\App\AppPool;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class VideoExtension extends AbstractExtension
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AppPool $apps,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('video', $this->renderVideo(...), AppExtension::options()),
            new TwigFunction('url_to_embed', $this->getEmbedCode(...)),
        ];
    }

    public function renderVideo(string $url, string $image, string $alternativeText = '', bool $forceUrl = false): string
    {
        $template = $this->apps->get()->getView('/component/video.html.twig');
        $youtube = $forceUrl ? null : $this->getYoutubeVideoUrl($url);

        return trim($this->twig->render($template, [
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

    private function getEmbedCode(string $embed_code): string
    {
        if (($id = $this->getYoutubeVideoUrl($embed_code)) !== '') {
            $template = $this->apps->get()->getView('/component/video_youtube_embed.html.twig');

            return $this->twig->render($template, ['id' => $id]);
        }

        return '';
    }
}
