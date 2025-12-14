<?php

namespace App\DataFixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Service\Attribute\Required;

class AppFixtures extends Fixture
{
    private ParameterBagInterface $params;

    private AppPool $apps;

    #[Required]
    public function setParameterBag(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    #[Required]
    public function setApps(AppPool $apps): void
    {
        $this->apps = $apps;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User()
            ->setEmail('contact@piedweb.com')
            ->setRoles([User::ROLE_DEFAULT]);

        $manager->persist($user);

        $medias = [
            'Pied Web Logo' => ['file' => 'piedweb-logo.png', 'mime' => 'image/png'],
            'Demo 1' => ['file' => '1.jpg', 'mime' => 'image/jpeg'],
            'Demo 2' => ['file' => '2.jpg', 'mime' => 'image/jpeg'],
            'Demo 3' => ['file' => '3.jpg', 'mime' => 'image/jpeg'],
        ];
        $media = [];
        foreach ($medias as $name => $data) {
            $media[$name] = new Media()
            ->setProjectDir($this->params->get('kernel.project_dir'))
            ->setStoreIn($this->params->get('pw.media_dir'))
            ->setMimeType($data['mime'])
            ->setSize(2)
            ->setDimensions([1000, 1000])
            ->setFileName($data['file'])
            ->setAlt($name)
            ->setHash();

            $manager->persist($media[$name]);
        }

        $manager->flush();

        $homepage = new Page()
            ->setH1('Welcome to Pushword !')
            ->setSlug('homepage')
            ->setMainImage($media['Demo 2'])
            ->setLocale('en')
            ->setCreatedAt(new DateTime('2 days ago'))
            ->setUpdatedAt(new DateTime('2 days ago'))
            ->setMainContent((string) file_get_contents(__DIR__.'/WelcomePage.md'));

        $homepage->setCustomProperty('mainImageFormat', 1);

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $homepage->setHost('localhost.dev');
        }

        $manager->persist($homepage);
        $manager->flush();

        $ksPage = new Page()
            ->setH1('Demo Page - Kitchen Sink  Markdown + Twig')
            ->setSlug('kitchen-sink')
            ->setMainImage($media['Demo 1'])
            ->setLocale('en')
            ->setParentPage($homepage)
            ->setCreatedAt(new DateTime('1 day ago'))
            ->setUpdatedAt(new DateTime('1 day ago'))
            ->setMainContent((string) file_get_contents(__DIR__.'/KitchenSink.md'));

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $ksPage->setHost('localhost.dev');
        }

        $manager->persist($ksPage);

        $ksPage = new Page()
            ->setH1('Demo Page - Kitchen Sink Block')
            ->setSlug('kitchen-sink')
            ->setMainImage($media['Demo 1'])
            ->setLocale('en')
            ->setParentPage($homepage)
            ->setCreatedAt(new DateTime('1 day ago'))
            ->setUpdatedAt(new DateTime('1 day ago'))
            ->setMainContent((string) file_get_contents(__DIR__.'/KitchenSink.md'));

        if (in_array('admin-block-editor.test', $this->apps->getHosts(), true)) {
            $ksPage->setHost('admin-block-editor.test');
        }

        $manager->persist($ksPage);

        $redirectionPage = new Page()
            ->setH1('Redirection')
            ->setSlug('pushword')
            ->setLocale('en')
            ->setCreatedAt(new DateTime('1 day ago'))
            ->setUpdatedAt(new DateTime('1 day ago'))
            ->setMainContent('Location: https://pushword.piedweb.com');

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $redirectionPage->setHost('localhost.dev');
        }

        $manager->persist($redirectionPage);

        // French page for localhost.dev to test multilingual menu
        $homepageFr = new Page()
            ->setH1('Bienvenue sur Pushword !')
            ->setSlug('homepage')
            ->setMainImage($media['Demo 3'])
            ->setLocale('fr')
            ->setCreatedAt(new DateTime('2 days ago'))
            ->setUpdatedAt(new DateTime('2 days ago'))
            ->setMainContent('Ceci est la page d\'accueil en français.');

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $homepageFr->setHost('localhost.dev');
        }

        $manager->persist($homepageFr);

        $manager->flush();

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $message = new Message()
                ->setContent('This is a default conversation message for localhost.dev. You can use this to test the conversation features.')
                ->setAuthorName('Demo User')
                ->setAuthorEmail('demo@localhost.dev')
                ->setHost('localhost.dev')
                ->setPublishedAt(new DateTime('1 day ago'));

            $manager->persist($message);

            // Create reviews from YAML file
            /** @var array<array{title: string, content: string, authorName: string, authorEmail: string, rating: int, publishedAt: string}>[] $reviewsData */
            $reviewsData = Yaml::parseFile(__DIR__.'/reviews.yaml');

            foreach ($reviewsData['reviews'] as $reviewData) {
                $review = new Review();
                $review->setTitle($reviewData['title']);
                $review->setContent($reviewData['content']);
                $review->setAuthorName($reviewData['authorName']);
                $review->setAuthorEmail($reviewData['authorEmail']);
                $review->setRating($reviewData['rating']);
                $review->setHost('localhost.dev');
                $review->setPublishedAt(new DateTime($reviewData['publishedAt']));
                $review->setTags('kitchen-sink');

                $manager->persist($review);
            }

            $manager->flush();
        }
    }
}
