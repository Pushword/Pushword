<?php

namespace Pushword\Facebook;

use PiedWeb\FacebookScraper\Client;
use PiedWeb\FacebookScraper\FacebookScraper;
use Pushword\Core\AutowiringTrait\RequiredApps;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\String\UnicodeString;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    use RequiredApps;

    /** @required */
    public ImageManager $imageManager;

    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('facebook_last_post', [$this, 'showFacebookLastPost'], ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    /**
     * @return array<mixed>|null
     */
    protected function getFacebookLastPost(string $id): ?array
    {
        Client::$userAgent = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/W.X.Y.Z Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $facebookScraper = new FacebookScraper($id);
        $posts = $facebookScraper->getPosts();

        // We retry getting the last result wich succeed to request facebook
        if (! isset($posts[0])) {
            $defaultCacheExpir = Client::$cacheExpir;
            Client::$cacheExpir = 0;
            $posts = $facebookScraper->getPosts();
            Client::$cacheExpir = $defaultCacheExpir;
        }

        return $posts[0] ?? null;
    }

    /**
     * @return mixed[]|string|null
     */
    public function showFacebookLastPost(Twig $twig, string $id, string $template = '/component/FacebookLastPost.html.twig')
    {
        $lastPost = $this->getFacebookLastPost($id);

        if (null === $lastPost) {
            return null;
        }

        if ('' === $template || '0' === $template) {
            return $lastPost;
        }

        if (isset($lastPost['images_hd'])) {
            $lastPost['images_hd'] = $this->importImages($lastPost);
        }

        $view = $this->apps->get()->getView($template, '@PushwordFacebook');

        return $twig->render($view, ['pageId' => $id, 'post' => $lastPost]);
    }

    /**
     * @param mixed[] $post
     *
     * @return \Pushword\Core\Entity\MediaInterface[]
     */
    private function importImages(array $post): array
    {
        $return = [];

        $unicodeString = new UnicodeString($post['text']); // @phpstan-ignore-line TODO switch to Object...

        foreach ($post['images_hd'] as $i => $image) { // @phpstan-ignore-line TODO switch to Object...
            $name = $unicodeString->truncate(25, '...').($i ? ' '.$i : '');  // @phpstan-ignore-line TODO switch to Object...
            $return[] = $this->imageManager->importExternal($image, $name, 'fb-'.$name); // @phpstan-ignore-line TODO switch to Object...
        }

        return $return;
    }
}
