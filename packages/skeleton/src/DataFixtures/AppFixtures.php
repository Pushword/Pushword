<?php

namespace App\DataFixtures;

use App\Entity\Media;
use App\Entity\Page;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Pushword\Core\Component\App\AppPool;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AppFixtures extends Fixture
{
    private ParameterBagInterface $params;

    private AppPool $apps;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setParameterBag(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setApps(AppPool $apps): void
    {
        $this->apps = $apps;
    }

    public function load(ObjectManager $manager): void
    {
        $user = (new User())
            ->setEmail('contact@piedweb.com')
            ->setRoles([User::ROLE_DEFAULT]);

        $manager->persist($user);

        $medias = [
            'Pied Web Logo' => 'piedweb-logo.png',
            'Demo 1' => '1.jpg',
            'Demo 2' => '2.jpg',
        ];
        $media = [];
        foreach ($medias as $name => $file) {
            $media[$name] = (new Media())
            ->setProjectDir($this->params->get('kernel.project_dir'))
            ->setStoreIn($this->params->get('pw.media_dir'))
            ->setMimeType('image/'.substr($file, -3))
            ->setSize(2)
            ->setDimensions([1000, 1000])
            ->setMedia($file)
            ->setName($name)
            ->setHash();

            $manager->persist($media[$name]);
        }

        $manager->flush();

        $homepage = (new Page())
            ->setH1('Welcome : this is your first page')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new DateTime('2 days ago'))
            ->setUpdatedAt(new DateTime('2 days ago'))
            ->setMainContent((string) file_get_contents(__DIR__.'/WelcomePage.md'));

        if ('localhost.dev' == $this->apps->getMainHost()) {
            $homepage->setHost('localhost.dev');
        }

        $manager->persist($homepage);
        $manager->flush();

        $ksPage = (new Page())
            ->setH1('Demo Page - Kitchen Sink  Markdown + Twig')
            ->setSlug('kitchen-sink')
            ->setMainImage($media['Demo 1'])
            ->setLocale('en')
            ->setParentPage($homepage)
            ->setCreatedAt(new DateTime('1 day ago'))
            ->setUpdatedAt(new DateTime('1 day ago'))
            ->setMainContent((string) file_get_contents(__DIR__.'/KitchenSink.md'))
            ->setCustomProperty('tag', 'testTag123');

        if ('localhost.dev' == $this->apps->getMainHost()) {
            $ksPage->setHost('localhost.dev');
        }

        $manager->persist($ksPage);

        $manager->flush();
    }
}
