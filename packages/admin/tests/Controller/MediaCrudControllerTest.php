<?php

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
    /**
     * The rotate buttons live under the image preview, not in the actions bar, so
     * they are routed via #[AdminRoute] rather than registered in configureActions.
     * This guards that the action still dispatches (no ForbiddenActionException)
     * and actually rotates the master.
     */
    public function testRotateActionRotatesImage(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $fileName = 'test-rotate-action-'.uniqid().'.png';
        $tempFile = $mediaDir.'/'.$fileName;
        $img = imagecreatetruecolor(4, 2);
        \assert(false !== $img);
        imagepng($img, $tempFile);

        $media = new Media()
            ->setProjectDir($projectDir)
            ->setStoreIn($mediaDir)
            ->setMimeType('image/png')
            ->setDimensions([4, 2])
            ->setSize(1)
            ->setFileName($fileName)
            ->setAlt('__rotate_action_test__');
        $em->persist($media);
        $em->flush();

        $id = $media->id;

        $router = self::getContainer()->get('router');
        $client->request(Request::METHOD_GET, $router->generate('admin_media_rotate_right', ['entityId' => $id]));
        self::assertResponseRedirects();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        /** @var MediaRepository $mediaRepo */
        $mediaRepo = self::getContainer()->get(MediaRepository::class);
        $rotated = $mediaRepo->find($id);
        self::assertInstanceOf(Media::class, $rotated);
        self::assertSame(2, $rotated->getWidth(), 'rotate-right must swap width/height');
        self::assertSame(4, $rotated->getHeight());

        $em->remove($rotated);
        $em->flush();
        @unlink($tempFile);
    }

    public function testHiddenFromAdminIsExcludedFromIndex(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

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
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        /** @var MediaRepository $mediaRepo */
        $mediaRepo = self::getContainer()->get(MediaRepository::class);
        $toRemove = $mediaRepo->findOneBy(['alt' => '__hidden_avatar_test__']);
        if (null !== $toRemove) {
            $em->remove($toRemove);
            $em->flush();
        }

        @unlink($tempFile);
    }
}
