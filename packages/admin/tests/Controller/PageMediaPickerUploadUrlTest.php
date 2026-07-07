<?php

namespace Pushword\Admin\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Regression test: the media picker upload/modal URLs are built by cloning the
 * current AdminUrlGenerator, which — while editing a Page — carries the Page's
 * entityId. If that entityId leaks into the Media "new" action URL, EasyAdmin
 * tries to load a Media with the Page's id and throws EntityNotFoundException.
 */
#[Group('integration')]
final class PageMediaPickerUploadUrlTest extends AbstractAdminTestClass
{
    public function testMediaPickerUrlsDoNotLeakPageEntityId(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($page, 'Fixture page "homepage" must exist');

        /** @var AdminUrlGenerator $urlGenerator */
        $urlGenerator = clone self::getContainer()->get(AdminUrlGenerator::class);
        $editUrl = $urlGenerator
            ->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction('edit')
            ->setEntityId($page->id)
            ->generateUrl();

        $parsed = parse_url($editUrl);
        $query = $parsed['query'] ?? '';
        $editPath = ($parsed['path'] ?? '/').('' !== $query ? '?'.$query : '');

        $crawler = $client->request(Request::METHOD_GET, $editPath);
        self::assertResponseIsSuccessful();

        $pickers = $crawler->filter('[data-pw-media-picker-upload-url]');
        self::assertGreaterThan(0, $pickers->count(), 'Page edit form must render at least one media picker');

        /** @var array<array{upload: string, modal: string}> $urls */
        $urls = $pickers->each(static fn (Crawler $picker): array => [
            'upload' => $picker->attr('data-pw-media-picker-upload-url') ?? '',
            'modal' => $picker->attr('data-pw-media-picker-modal-url') ?? '',
        ]);

        foreach ($urls as $url) {
            self::assertStringNotContainsString('entityId', $url['upload'], 'Upload URL must not carry the Page entityId: '.$url['upload']);
            self::assertStringNotContainsString('entityId', $url['modal'], 'Modal URL must not carry the Page entityId: '.$url['modal']);
        }

        $uploadUrl = $urls[0]['upload'];
        self::assertNotEmpty($uploadUrl);

        // Behavioural guard: the Media "new" action must actually load (200),
        // not 404 with EntityNotFoundException as it did with the leaked entityId.
        $uploadParsed = parse_url($uploadUrl);
        $uploadQuery = $uploadParsed['query'] ?? '';
        $uploadPath = ($uploadParsed['path'] ?? '/').('' !== $uploadQuery ? '?'.$uploadQuery : '');

        $client->request(Request::METHOD_GET, $uploadPath);
        self::assertResponseIsSuccessful();
    }
}
