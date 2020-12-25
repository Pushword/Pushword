<?php

namespace App\DataFixtures;

use App\Entity\Media;
use App\Entity\Page;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager)
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
        foreach ($medias as $name => $file) {
            $media[$name] = (new Media())
            ->setRelativeDir('media')
            ->setMimeType('image/'.substr($file, -3))
            ->setSize(2)
            ->setDimensions([1000, 1000])
            ->setSlug($file)
            ->setMedia($file)
            ->setName($name);

            $manager->persist($media[$name]);
        }
        $manager->flush();

        $homepage = (new Page())
            ->setH1('Welcome : this is your first page')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new DateTime('2 days ago'))
            ->setUpdatedAt(new DateTime('2 days ago'))
            ->setMainContent(file_get_contents(__DIR__.'/WelcomePage.md'));

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
            ->setMainContent(file_get_contents(__DIR__.'/KitchenSink.md'));

        $manager->persist($ksPage);

        $manager->flush();
    }
}
