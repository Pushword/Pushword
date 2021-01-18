<?php

namespace Pushword\Facebook;

use PiedWeb\FacebookScraper\Client;
use PiedWeb\FacebookScraper\FacebookScraper;
use Pushword\Core\AutowiringTrait\RequiredApps;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\MediaCacheGenerator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\UnicodeString;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    use RequiredApps;

    private string $mediaDir;

    /** @required */
    public MediaCacheGenerator $mediaCacheGenerator;

    public function setMediaDir(string $mediaDir)
    {
        $this->mediaDir = $mediaDir;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('facebook_last_post', [$this, 'showFacebookLastPost'], ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    protected function getFacebookLastPost(string $id): ?array
    {
        $fbScraper = new FacebookScraper($id);
        $posts = $fbScraper->getPosts();

        // We retry getting the last result wich succeed to request facebook
        if (! isset($posts[0])) {
            $defaultCacheExpir = Client::$cacheExpir;
            Client::$cacheExpir = 0;
            $posts = $fbScraper->getPosts();
            Client::$cacheExpir = $defaultCacheExpir;
        }

        return $posts[0] ?? null;
    }

    public function showFacebookLastPost(Twig $twig, string $id, string $template = '/component/FacebookLastPost.html.twig')
    {
        $lastPost = $this->getFacebookLastPost($id);

        if (! $lastPost) {
            return null;
        }

        if (! $template) {
            return $lastPost;
        }

        if ($lastPost['images_hd']) {
            $lastPost['images_hd'] = $this->importImages($lastPost);
        }

        $view = $this->apps->get()->getView($template, $twig, '@PushwordFacebook');

        return $twig->render($view, ['pageId' => $id, 'post' => $lastPost]);
    }

    private function importImages($post): array
    {
        $return = [];

        $alt = new UnicodeString($post['text']);

        foreach ($post['images_hd'] as $i => $image) {
            $return[] = $this->importImage($image, $alt->truncate(50, '...').($i ? ' '.$i : ''));
        }

        return $return;
    }

    private function importImage($image, $alt = ''): Media
    {
        $alt = str_replace(["\n", '"'], ' ', $alt);
        $fs = new FileSystem();

        $imgSize = getimagesize($image);

        $fileName = 'fb-'.pathinfo($image, \PATHINFO_BASENAME).'.'.str_replace('image/', '', $imgSize['mime']);

        $newFilePath = $this->mediaDir.'/'.$fileName;

        $media = new Media();
        $media
            ->setRelativeDir('media')
            ->setMimeType($imgSize['mime'])
            ->setSize(filesize($image))
            ->setDimensions([$imgSize[0], $imgSize[1]])
            ->setSlug($fileName)
            ->setMedia($fileName)
            ->setName($alt);

        if (! file_exists($newFilePath)) {
            $fs->copy($image, $newFilePath);

            $this->mediaCacheGenerator->generateCache($media);
        }

        return $media;
    }
}
