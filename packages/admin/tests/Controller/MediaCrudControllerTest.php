<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class MediaCrudControllerTest extends AbstractAdminTestClass
{
    public function testHiddenFromAdminIsExcludedFromIndex(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        /** @var EntityManager $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = static::getContainer()->getParameter('pw.media_dir');

        $tempFile = $mediaDir.'/test-hidden-avatar.png';
        $img = imagecreatetruecolor(1, 1);
        \assert(false !== $img);
        imagepng($img, $tempFile);

        $hidden = new Media()
            ->setProjectDir($projectDir)
            ->setStoreIn($mediaDir)
            ->setMimeType('image/png')
            ->setSize(1)
            ->setFileName('test-hidden-avatar.png')
            ->setAlt('__hidden_avatar_test__');
        $hidden->hiddenFromAdmin = true;

        $em->persist($hidden);
        $em->flush();

        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_media_list'));
        self::assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('__hidden_avatar_test__', $content, 'Hidden media must not appear in admin index');
        self::assertStringContainsString('Pied Web Logo', $content, 'Normal media must still appear');

        // Re-fetch after HTTP request rebuilt the container
        /** @var EntityManager $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        /** @var MediaRepository $mediaRepo */
        $mediaRepo = static::getContainer()->get(MediaRepository::class);
        $toRemove = $mediaRepo->findOneBy(['alt' => '__hidden_avatar_test__']);
        if (null !== $toRemove) {
            $em->remove($toRemove);
            $em->flush();
        }

        @unlink($tempFile);
    }
}
