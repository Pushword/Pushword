<?php

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Performance regression: the page-edit main-image picker must render only the
 * currently-selected media as a <select> option — the picker modal browses the
 * library — never every Media row (on a media-heavy site that is tens of
 * thousands of options ≈ hundreds of MB and seconds of render).
 *
 * A media picked in the modal (an id absent from the rendered options) must
 * still validate on submit: the by-id lookup is decoupled from the render list.
 *
 * @see \Pushword\Admin\Form\ChoiceList\SelectedMediaChoiceLoader
 */
#[Group('integration')]
final class PageMainImagePickerChoicesTest extends AbstractAdminTestClass
{
    public function testMainImageSelectRendersOnlySelectedAndAcceptsNewlyPickedId(): void
    {
        $client = $this->loginUser();

        $mediaA = $this->createMedia($client, 'main-image-a', 30, 20);
        $mediaB = $this->createMedia($client, 'main-image-b', 31, 21);
        self::assertNotSame($mediaA, $mediaB);

        $em = $this->getEntityManager();
        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertInstanceOf(Page::class, $page);
        $pageId = $page->id;
        self::assertNotNull($pageId);

        $page->setMainImage($em->getRepository(Media::class)->find($mediaA));
        $em->flush();

        $editPath = $this->buildEditPath($pageId);
        $crawler = $client->request(Request::METHOD_GET, $editPath);
        self::assertResponseIsSuccessful();

        $optionValues = array_values(array_filter(
            $crawler->filter('select#Page_mainImage option')->each(static fn (Crawler $option): string => (string) $option->attr('value')),
            static fn (string $value): bool => '' !== $value,
        ));

        // Only the selected media is rendered as an option — not the whole library.
        self::assertSame(
            [(string) $mediaA],
            $optionValues,
            'The main-image <select> must render only the selected media, not every Media row.',
        );

        // A media picked in the modal (absent from the rendered options) must
        // still validate and persist on submit.
        $formNode = $crawler->filter('#Page_mainImage')->closest('form');
        self::assertNotNull($formNode);
        $form = $formNode->form();
        $form->disableValidation();
        $form['Page[mainImage]'] = (string) $mediaB;
        $client->submit($form);
        self::assertResponseRedirects();

        $em->clear();
        $reloaded = $pageRepo->find($pageId);
        self::assertInstanceOf(Page::class, $reloaded);
        self::assertSame(
            $mediaB,
            $reloaded->getMainImage()?->id,
            'A media picked in the modal (not in the rendered options) must validate and persist.',
        );
    }

    private function buildEditPath(int $pageId): string
    {
        /** @var AdminUrlGenerator $urlGenerator */
        $urlGenerator = clone self::getContainer()->get(AdminUrlGenerator::class);
        $editUrl = $urlGenerator
            ->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction('edit')
            ->setEntityId($pageId)
            ->generateUrl();

        $parsed = parse_url($editUrl);
        $query = $parsed['query'] ?? '';

        return ($parsed['path'] ?? '/').('' !== $query ? '?'.$query : '');
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function createMedia(KernelBrowser $client, string $name, int $width, int $height): int
    {
        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrf = (string) $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');

        $tempFile = sys_get_temp_dir().'/'.$name.'.jpg';
        $img = imagecreatetruecolor($width, $height);
        \assert(false !== $img);
        imagejpeg($img, $tempFile);

        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrf,
            'originalHash' => sha1_file($tempFile),
        ], ['file' => new UploadedFile($tempFile, $name.'.jpg', 'image/jpeg', null, true)]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $data);
        self::assertIsInt($data['id']);

        return $data['id'];
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
