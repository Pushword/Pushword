<?php

namespace Pushword\AdminBlockEditor\Tests;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

#[Group('integration')]
final class ConvertBlockFormatCommandTest extends KernelTestCase
{
    /** @var int[] page IDs to clean up after each test */
    private array $createdPageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createdPageIds = [];
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testFlattensImageBlockToMediaAndCaption(): void
    {
        self::bootKernel();
        $pageId = $this->createPage('block-upgrade-image', [
            'blocks' => [[
                'type' => 'image',
                'data' => ['file' => ['media' => 'photo.jpg', 'name' => 'From file name'], 'caption' => 'A caption'],
            ]],
        ]);

        $this->runUpgradeCommand();

        $block = $this->firstBlockOf($pageId);
        self::assertSame('photo.jpg', $block['data']['media'] ?? null);
        self::assertSame('A caption', $block['data']['caption'] ?? null);
        self::assertArrayNotHasKey('file', $block['data']);
    }

    public function testImageBlockFallsBackToTheFileNameAsCaption(): void
    {
        self::bootKernel();
        $pageId = $this->createPage('block-upgrade-image-fallback', [
            'blocks' => [[
                'type' => 'image',
                'data' => ['file' => ['media' => 'photo.jpg', 'name' => 'From file name']],
            ]],
        ]);

        $this->runUpgradeCommand();

        $block = $this->firstBlockOf($pageId);
        self::assertSame('From file name', $block['data']['caption'] ?? null);
    }

    public function testGalleryBlockBecomesAListOfMediaNames(): void
    {
        self::bootKernel();
        $pageId = $this->createPage('block-upgrade-gallery', [
            'blocks' => [[
                'type' => 'gallery',
                'data' => [
                    ['file' => ['media' => '1.jpg'], 'url' => '/media/default/1.jpg', 'caption' => 'Demo 1'],
                    ['file' => ['media' => '2.jpg']],
                ],
            ]],
        ]);

        $this->runUpgradeCommand();

        $block = $this->firstBlockOf($pageId);
        self::assertSame(['1.jpg', '2.jpg'], $block['data']);
    }

    public function testAttachesBlockKeepsOnlyUrlNameAndSize(): void
    {
        self::bootKernel();
        $pageId = $this->createPage('block-upgrade-attaches', [
            'blocks' => [[
                'type' => 'attaches',
                'data' => [
                    'file' => [
                        'url' => 'https://example.com/file.pdf',
                        'size' => '1024',
                        'name' => 'document.pdf',
                        'extension' => 'pdf',
                        'mimeType' => 'application/pdf',
                    ],
                    'title' => 'Document Title',
                ],
            ]],
        ]);

        $this->runUpgradeCommand();

        $block = $this->firstBlockOf($pageId);
        self::assertSame('Document Title', $block['data']['title'] ?? null);
        self::assertSame(
            ['url' => 'https://example.com/file.pdf', 'name' => 'document.pdf', 'size' => '1024'],
            $block['data']['file'] ?? null
        );
    }

    public function testLeavesMarkdownContentAlone(): void
    {
        self::bootKernel();
        $em = $this->getEntityManager();

        $page = new Page();
        $page->h1 = 'Block Upgrade Markdown';
        $page->slug = 'block-upgrade-markdown';
        $page->locale = 'en';
        $page->setMainContent('# Just markdown, no blocks here.');

        $em->persist($page);
        $em->flush();
        $this->createdPageIds[] = (int) $page->id;
        $pageId = (int) $page->id;

        $commandTester = $this->runUpgradeCommand();
        self::assertSame(0, $commandTester->getStatusCode());

        $em->clear();
        $reloaded = $em->find(Page::class, $pageId);
        self::assertNotNull($reloaded);
        self::assertSame('# Just markdown, no blocks here.', $reloaded->getMainContent());
    }

    private function runUpgradeCommand(): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:block:upgrade'));
        $commandTester->execute([]);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    /** @param array<string, mixed> $editorJs */
    private function createPage(string $slug, array $editorJs): int
    {
        $em = $this->getEntityManager();

        $page = new Page();
        $page->h1 = 'Block Upgrade Test';
        $page->slug = $slug;
        $page->locale = 'en';
        $page->setMainContent(json_encode($editorJs, \JSON_THROW_ON_ERROR));

        $em->persist($page);
        $em->flush();

        $this->createdPageIds[] = (int) $page->id;

        return (int) $page->id;
    }

    /** @return array{type: string, data: array<mixed>} */
    private function firstBlockOf(int $pageId): array
    {
        $em = $this->getEntityManager();
        $em->clear();

        $page = $em->find(Page::class, $pageId);
        self::assertNotNull($page);

        /** @var array{blocks: array<int, array{type: string, data: array<mixed>}>} $decoded */
        $decoded = json_decode($page->getMainContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded['blocks'][0];
    }

    private function cleanupTestData(): void
    {
        try {
            $em = $this->getEntityManager();
            if (! $em->isOpen()) {
                return;
            }

            $em->clear();

            foreach ($this->createdPageIds as $pageId) {
                $page = $em->find(Page::class, $pageId);
                if (null !== $page) {
                    $em->remove($page);
                }
            }

            $em->flush();
        } catch (Throwable) {
        }
    }
}
