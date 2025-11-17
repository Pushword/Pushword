<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

class AiIndexCommandTest extends KernelTestCase
{
    private string $exportDir;

    /** @var array<string> */
    private array $createdPageSlugs = [];

    /** @var array<string> */
    private array $createdMediaNames = [];

    private function executeCommand(?string $host = null, string $exportDir = ''): CommandTester
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('pw:ai-index');
        $commandTester = new CommandTester($command);

        $params = [];
        if (null !== $host) {
            $params['host'] = $host;
        }

        $params['exportDir'] = '' !== $exportDir ? $exportDir : $this->exportDir;

        $commandTester->execute($params);

        return $commandTester;
    }

    private function createTestMedia(string $name, string $mimeType = 'image/jpeg'): Media
    {
        self::bootKernel();

        /** @var EntityManager */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'));
        $media->setStoreIn($mediaDir);
        $media->setMedia($name);
        $media->setName('Test '.$name);
        $media->setMimeType($mimeType);

        $mediaFilePath = $mediaDir.'/'.$name;
        if (! is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        file_put_contents($mediaFilePath, 'fake image content');

        $em->persist($media);
        $em->flush();

        $this->createdMediaNames[] = $name;

        return $media;
    }

    /**
     * @param string[] $tags
     */
    private function createTestPage(string $slug, string $content = '', ?Page $parentPage = null, array $tags = [], ?string $searchExcrept = null): Page
    {
        self::bootKernel();

        /** @var EntityManager */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        /** @var AppPool */
        $apps = self::getContainer()->get(AppPool::class);

        $page = new Page();
        $page->setSlug($slug);
        $page->setH1('Test '.$slug);
        $page->setMainContent($content);
        $page->setHost($apps->get()->getMainHost());
        $page->setCreatedAt(new DateTime());
        if (null !== $parentPage) {
            // Retrieve parent page from database so it's in the same EntityManager
            $parentPage = $em->getRepository(Page::class)->findOneBy(['slug' => $parentPage->getSlug()]);
            if (null !== $parentPage) {
                $page->setParentPage($parentPage);
            }
        }

        if ([] !== $tags) {
            $page->setTags($tags);
        }

        if (null !== $searchExcrept) {
            $page->setSearchExcrept($searchExcrept);
        }

        $em->persist($page);
        $em->flush();

        $this->createdPageSlugs[] = $slug;

        return $page;
    }

    /**
     * @return list<string>|null
     */
    private function getDataFromCsv(string $slug, string $filename = 'pages.csv'): ?array
    {
        $pagesFile = $this->exportDir.'/'.$filename;
        if (! file_exists($pagesFile)) {
            return null;
        }

        $lines = file($pagesFile, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $lines) {
            return null;
        }

        foreach ($lines as $line) {
            $data = str_getcsv($line, ',', '"', '\\');
            if (isset($data[0]) && $data[0] === $slug) {
                return $data; // @phpstan-ignore-line
            }
        }

        return null;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = static::createKernel();
        $this->exportDir = $kernel->getCacheDir().'/test-ai-index-'.uniqid();
        (new Filesystem())->mkdir($this->exportDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        // Clean up created entities
        if ([] !== $this->createdPageSlugs || [] !== $this->createdMediaNames) {
            try {
                self::bootKernel();
                /** @var EntityManager */
                $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

                foreach ($this->createdPageSlugs as $slug) {
                    $page = $em->getRepository(Page::class)->findOneBy(['slug' => $slug]);
                    if (null !== $page) {
                        $em->remove($page);
                    }
                }

                foreach ($this->createdMediaNames as $mediaName) {
                    $media = $em->getRepository(Media::class)->findOneBy(['media' => $mediaName]);
                    if (null !== $media) {
                        $em->remove($media);
                    }
                }

                $em->flush();
            } catch (Throwable) {
                // Ignore errors during cleanup
            }
        }

        if (is_dir($this->exportDir)) {
            (new Filesystem())->remove($this->exportDir);
        }

        parent::tearDown();
    }

    public function testExecuteWithoutHost(): void
    {
        $commandTester = $this->executeCommand();

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Generating pages.csv...', $output);
        self::assertStringContainsString('Generating medias.csv...', $output);
        self::assertStringContainsString('File generated.', $output);
        self::assertEquals(0, $commandTester->getStatusCode());
        self::assertFileExists($this->exportDir.'/pages.csv');
        self::assertFileExists($this->exportDir.'/medias.csv');
    }

    public function testExecuteWithHost(): void
    {
        $commandTester = $this->executeCommand('pushword.piedweb.com');

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Generating pages.csv...', $output);
        self::assertStringContainsString('Generating medias.csv...', $output);
        self::assertStringContainsString('File generated.', $output);
        self::assertEquals(0, $commandTester->getStatusCode());
    }

    public function testCommandWithEmptyExportDir(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('pw:ai-index');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Generating pages.csv...', $output);
        self::assertEquals(0, $commandTester->getStatusCode());
    }

    public function testpagesContainsCreatedPageWithMedia(): void
    {
        $mediaName = 'test-image-'.uniqid().'.jpg';
        $this->createTestMedia($mediaName, 'image/jpeg');

        $pageSlug = 'test-page-with-media-'.uniqid();
        $this->createTestPage($pageSlug, 'This page uses an image: '.$mediaName.' in the content.');

        $this->executeCommand();

        $pageData = $this->getDataFromCsv($pageSlug);
        self::assertNotNull($pageData, 'Page should be found in CSV');
        self::assertStringContainsString($mediaName, $pageData[5] ?? '', 'Media should be listed in mediaUsed');
        self::assertNotEmpty($pageData[5] ?? '', 'mediaUsed should not be empty');

        $mediaData = $this->getDataFromCsv($mediaName, 'medias.csv');
        self::assertNotNull($mediaData, 'Media should be found in CSV');
        self::assertStringContainsString($pageSlug, $mediaData[3] ?? '', 'Page should be listed in usedInPages');
        self::assertNotEmpty($mediaData[3] ?? '', 'usedInPages should not be empty');
        self::assertEquals('image/jpeg', $mediaData[1], 'mimeType should be correct');
    }

    public function testpagesContainsPageLinks(): void
    {
        $parentSlug = 'parent-page-'.uniqid();
        $parentPage = $this->createTestPage($parentSlug, 'This is the parent page.');

        $childSlug = 'child-page-'.uniqid();
        $this->createTestPage($childSlug, 'This page links to '.$parentSlug.' and mentions it.', $parentPage);

        $this->executeCommand();

        $childPageData = $this->getDataFromCsv($childSlug);
        self::assertNotNull($childPageData, 'Child page should be found in CSV');
        self::assertEquals($parentSlug, $childPageData[6] ?? '', 'Parent page should be listed in parentPage');
        self::assertStringContainsString($parentSlug, $childPageData[7] ?? '', 'Parent page should be in pageLinked');
        self::assertNotEmpty($childPageData[7] ?? '', 'pageLinked should not be empty');
    }

    public function testpagesContainsTagsAndSummary(): void
    {
        $pageSlug = 'test-page-tags-'.uniqid();
        $this->createTestPage(
            $pageSlug,
            'This is the main content of the page.',
            null,
            ['tag1', 'tag2', 'tag3'],
            'This is a summary of the page.'
        );

        $this->executeCommand();

        $pageData = $this->getDataFromCsv($pageSlug);
        self::assertNotNull($pageData, 'Page should be found in CSV');
        $tags = $pageData[3] ?? '';
        self::assertStringContainsString('tag1', $tags);
        self::assertStringContainsString('tag2', $tags);
        self::assertStringContainsString('tag3', $tags);
        self::assertEquals('This is a summary of the page.', $pageData[4] ?? '');
        self::assertEquals('37', $pageData[8] ?? '', 'Content length should be 37 characters');
    }

    public function testMultipleMediaExtraction(): void
    {
        $media1Name = 'test-image-1-'.uniqid().'.jpg';
        $this->createTestMedia($media1Name, 'image/jpeg');

        $media2Name = 'test-image-2-'.uniqid().'.jpg';
        $this->createTestMedia($media2Name, 'image/png');

        $pageSlug = 'test-page-multiple-media-'.uniqid();
        $this->createTestPage($pageSlug, 'This page uses two images: '.$media1Name.' and '.$media2Name.' in the content.');

        $this->executeCommand();

        $pageData = $this->getDataFromCsv($pageSlug);
        self::assertNotNull($pageData, 'Page should be found in CSV');
        self::assertStringContainsString($media1Name, $pageData[5] ?? '', 'First media should be in mediaUsed');
        self::assertStringContainsString($media2Name, $pageData[5] ?? '', 'Second media should be in mediaUsed');

        $media1Data = $this->getDataFromCsv($media1Name, 'medias.csv');
        self::assertNotNull($media1Data, 'First media should be found in CSV');
        self::assertStringContainsString($pageSlug, $media1Data[3] ?? '', 'Page should be in usedInPages for first media');

        $media2Data = $this->getDataFromCsv($media2Name, 'medias.csv');
        self::assertNotNull($media2Data, 'Second media should be found in CSV');
        self::assertStringContainsString($pageSlug, $media2Data[3] ?? '', 'Page should be in usedInPages for second media');
    }

    public function testMultiplePageLinksExtraction(): void
    {
        $page1Slug = 'linked-page-1-'.uniqid();
        $this->createTestPage($page1Slug, 'This is page 1.');

        $page2Slug = 'linked-page-2-'.uniqid();
        $this->createTestPage($page2Slug, 'This is page 2.');

        $mainPageSlug = 'main-page-'.uniqid();
        $this->createTestPage($mainPageSlug, 'This page links to '.$page1Slug.' and also mentions '.$page2Slug.' in the content.');

        $this->executeCommand();

        $mainPageData = $this->getDataFromCsv($mainPageSlug);
        self::assertNotNull($mainPageData, 'Main page should be found in CSV');
        self::assertStringContainsString($page1Slug, $mainPageData[7] ?? '', 'First linked page should be in pageLinked');
        self::assertStringContainsString($page2Slug, $mainPageData[7] ?? '', 'Second linked page should be in pageLinked');
    }

    public function testMediaNotUsedIsNotListed(): void
    {
        $unusedMediaName = 'unused-media-'.uniqid().'.jpg';
        $this->createTestMedia($unusedMediaName);

        $pageSlug = 'test-page-no-media-'.uniqid();
        $this->createTestPage($pageSlug, 'This page does not use any media.');

        $this->executeCommand();

        $pageData = $this->getDataFromCsv($pageSlug);
        self::assertNotNull($pageData, 'Page should be found in CSV');
        self::assertEmpty($pageData[5] ?? '', 'mediaUsed should be empty as no media is used');

        $mediaData = $this->getDataFromCsv($unusedMediaName, 'medias.csv');
        self::assertNotNull($mediaData, 'Media should be found in CSV');
        self::assertEmpty($mediaData[3] ?? '', 'usedInPages should be empty as media is not used anywhere');
    }
}
