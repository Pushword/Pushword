<?php

namespace App\DataFixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The demo content of a fresh Pushword install.
 *
 * Every page is tagged `demo`, so you can drop the whole set once you have seen it:
 *
 *     php bin/console pw:page:delete --tag=demo
 *
 * With `pushword/repurpose` installed it also seeds one carousel (`Carousel.json`);
 * that one is not a page, so you delete it from Social posts in the admin.
 *
 * Edit this file to seed your own content instead, then reload it with
 * `php bin/console doctrine:fixtures:load`. It purges the database first.
 */
class AppFixtures extends Fixture
{
    private ParameterBagInterface $params;

    #[Required]
    public function setParams(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    public function load(ObjectManager $manager): void
    {
        foreach (['Demo 1' => '1.jpg', 'Demo 2' => '2.jpg', 'Demo 3' => '3.jpg'] as $alt => $fileName) {
            $manager->persist(new Media()
                ->setProjectDir((string) $this->params->get('kernel.project_dir'))
                ->setStoreIn((string) $this->params->get('pw.media_dir'))
                ->setMimeType('image/jpeg')
                ->setSize(2)
                ->setDimensions([1000, 1000])
                ->setFileName($fileName)
                ->setAlt($alt)
                ->setHash());
        }

        $locale = (string) $this->params->get('kernel.default_locale');
        // The column maps to Doctrine's mutable `datetime` type: not DateTimeImmutable.
        $publishedAt = new DateTime('-1 day');

        foreach ([
            ['homepage', 'Welcome to Pushword', 'Homepage.md'],
            ['getting-started', 'Getting started', 'GettingStarted.md'],
            ['examples', 'What you can write', 'Examples.md'],
            ['contact', 'Contact', 'Contact.md'],
        ] as [$slug, $h1, $file]) {
            $page = new Page();
            $page->slug = $slug;
            $page->h1 = $h1;
            $page->title = $h1;
            $page->locale = $locale;
            $page->publishedAt = $publishedAt;
            $page->setTags('demo');
            $page->mainContent = (string) file_get_contents(__DIR__.'/'.$file);

            $manager->persist($page);
        }

        $manager->flush();

        $this->loadCarousel($manager);
    }

    /**
     * `Examples.md` for social slides: one carousel of the `examples` page whose
     * every slide renders one feature and says which — the text stack, anchors and
     * alignment, per-slide palettes and highlights, free text boxes, focal-point
     * crops, split frames and deck-wide background effects.
     *
     * The spec lives in `Carousel.json`, the very shape the API, the JSON Schema
     * and `pw:repurpose:validate` speak, so it doubles as something to copy from:
     *
     *     php bin/console pw:repurpose:validate src/DataFixtures/Carousel.json
     *
     * It leaves `host` empty, like the pages above: that means the main site.
     */
    private function loadCarousel(ObjectManager $manager): void
    {
        if (! class_exists(SocialPost::class)) {
            return;
        }

        /** @var array<string, mixed> $spec */
        $spec = json_decode((string) file_get_contents(__DIR__.'/Carousel.json'), true, flags: \JSON_THROW_ON_ERROR);

        $post = new SocialPost();
        $post->spec = $spec;

        $manager->persist($post);
        $manager->flush();
    }
}
