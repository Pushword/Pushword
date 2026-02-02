<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class FlatSyncTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        $this->cleanFixtures();
        parent::tearDown();
    }

    public function testImportReplacesMarkdownLinks(): void
    {
        self::bootKernel();
        $this->cleanGlobalIndexBeforeTest();
        $this->prepareFixtures();

        /** @var FlatFileSync $sync */
        $sync = self::getContainer()->get(FlatFileSync::class);
        $sync->import('pushword.piedweb.com');

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = $em->getRepository(Page::class)->findOneBy(['slug' => 'test-link']);

        self::assertInstanceOf(Page::class, $page);
        self::assertStringContainsString('](/test-content)', $page->getMainContent());
    }

    private function prepareFixtures(): void
    {
        $filesystem = new Filesystem();
        $contentDir = $this->getContentDir();

        $filesystem->mkdir($contentDir.'/media');
        $filesystem->copy(__DIR__.'/content/test-content.md', $contentDir.'/test-content.md', true);
        $filesystem->copy(__DIR__.'/content/test-link.md', $contentDir.'/test-link.md', true);
        $filesystem->copy(__DIR__.'/content/media/logo-test.png', $contentDir.'/media/logo-test.png', true);
        $filesystem->copy(__DIR__.'/content/media/index.csv', $contentDir.'/media/index.csv', true);
    }

    private function cleanFixtures(): void
    {
        @unlink($this->getContentDir().'/test-content.md');
        @unlink($this->getContentDir().'/test-link.md');
        @unlink($this->getContentDir().'/media/logo-test.png');
        @unlink($this->getContentDir().'/media/index.csv');
        @unlink($this->getMediaDir().'/logo-test.png');
        @unlink($this->getMediaDir().'/index.csv');
    }

    private function cleanGlobalIndexBeforeTest(): void
    {
        // Remove any existing index.csv from global media dir to avoid ID conflicts
        @unlink($this->getMediaDir().'/index.csv');
    }

    private function getContentDir(): string
    {
        /** @var non-falsy-string $dir */
        $dir = self::getContainer()->getParameter('kernel.project_dir').'/../docs/content';

        return $dir;
    }

    private function getMediaDir(): string
    {
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        return $mediaDir;
    }
}
