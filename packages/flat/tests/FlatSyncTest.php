<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\FlatFileSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class FlatSyncTest extends KernelTestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->cleanFixtures();
        parent::tearDown();
    }

    public function testImportReplacesMarkdownLinks(): void
    {
        $this->cleanGlobalIndexBeforeTest();
        $this->prepareFixtures();

        /** @var FlatFileSync $sync */
        $sync = self::getContainer()->get(FlatFileSync::class);
        $sync->import('localhost.dev');

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = $em->getRepository(Page::class)->findOneBy(['slug' => 'test-link']);

        self::assertInstanceOf(Page::class, $page);
        self::assertStringContainsString('](/test-content)', $page->getMainContent());

        // Cleanup imported pages
        $testContent = $em->getRepository(Page::class)->findOneBy(['slug' => 'test-content']);
        if ($testContent instanceof Page) {
            $em->remove($testContent);
        }

        $em->remove($page);
        $em->flush();
    }

    private function prepareFixtures(): void
    {
        $filesystem = new Filesystem();

        $filesystem->mkdir($this->contentDir.'/media');
        $filesystem->copy(__DIR__.'/content/test-content.md', $this->contentDir.'/test-content.md', true);
        $filesystem->copy(__DIR__.'/content/test-link.md', $this->contentDir.'/test-link.md', true);
        $filesystem->copy(__DIR__.'/content/media/logo-test.png', $this->contentDir.'/media/logo-test.png', true);
        $filesystem->copy(__DIR__.'/content/media/index.csv', $this->contentDir.'/media/index.csv', true);
    }

    private function cleanFixtures(): void
    {
        new Filesystem()->remove([
            $this->contentDir.'/test-content.md',
            $this->contentDir.'/test-link.md',
            $this->contentDir.'/media/logo-test.png',
            $this->contentDir.'/media/index.csv',
            $this->getMediaDir().'/logo-test.png',
            $this->getMediaDir().'/index.csv',
        ]);
    }

    private function cleanGlobalIndexBeforeTest(): void
    {
        new Filesystem()->remove($this->getMediaDir().'/index.csv');
    }

    private function getMediaDir(): string
    {
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        return $mediaDir;
    }
}
