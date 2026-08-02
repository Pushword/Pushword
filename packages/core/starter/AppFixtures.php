<?php

namespace App\DataFixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The demo content of a fresh Pushword install.
 *
 * Every page is tagged `demo`, so you can drop the whole set once you have seen it:
 *
 *     php bin/console pw:page:delete --tag=demo
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
    }
}
