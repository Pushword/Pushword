<?php

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\FlatFileSync;
use Pushword\Snippet\Entity\Snippet;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class SnippetSyncTest extends KernelTestCase
{
    private string $snippetsDir;

    private EntityManagerInterface $em;

    private FlatFileSync $sync;

    private string $slug;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->sync = $container->get(FlatFileSync::class);
        $this->snippetsDir = $container->get(FlatFileContentDirFinder::class)->get('localhost.dev').'/snippets';
        $this->slug = 'sync-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->snippetsDir.'/'.$this->slug.'.md');

        $snippet = $this->em->getRepository(Snippet::class)->findOneBy(['slug' => $this->slug]);
        if ($snippet instanceof Snippet) {
            $this->em->remove($snippet);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testExportWritesMarkdownWithFrontMatter(): void
    {
        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug($this->slug);
        $snippet->setName('Sync Test');
        $snippet->setTags('alpha beta');
        $snippet->setContent('# Hello flat sync');

        $this->em->persist($snippet);
        $this->em->flush();

        $this->sync->export('localhost.dev', entity: 'snippet');

        $path = $this->snippetsDir.'/'.$this->slug.'.md';
        self::assertFileExists($path);

        $written = (string) file_get_contents($path);
        self::assertStringContainsString('name: ', $written);
        self::assertStringContainsString('Sync Test', $written);
        self::assertStringContainsString('alpha', $written);
        self::assertStringContainsString('# Hello flat sync', $written);
    }

    public function testImportDeletesSnippetWhoseFileWasRemoved(): void
    {
        // A: present as a file, must survive the import.
        new Filesystem()->dumpFile($this->snippetsDir.'/'.$this->slug.'.md', "---\nname: Keep\n---\nkeep\n");

        // B: in the database only (its file was removed), must be deleted.
        $orphanSlug = 'orphan-'.uniqid();
        $orphan = new Snippet();
        $orphan->host = 'localhost.dev';
        $orphan->setSlug($orphanSlug);
        $orphan->setName('Orphan');
        $orphan->setContent('gone');
        $this->em->persist($orphan);
        $this->em->flush();

        $this->sync->import('localhost.dev', entity: 'snippet');

        $repo = $this->em->getRepository(Snippet::class);
        self::assertNull($repo->findOneBy(['slug' => $orphanSlug]), 'snippet with no file should be deleted');
        self::assertInstanceOf(Snippet::class, $repo->findOneBy(['slug' => $this->slug]), 'snippet with a file should be kept');
    }

    public function testImportCreatesAndUpdatesSnippetFromFile(): void
    {
        new Filesystem()->dumpFile(
            $this->snippetsDir.'/'.$this->slug.'.md',
            "---\nname: 'Imported snippet'\ntags:\n  - news\n---\n\nImported **body**\n",
        );

        $this->sync->import('localhost.dev', entity: 'snippet');

        $snippet = $this->em->getRepository(Snippet::class)->findOneBy(['slug' => $this->slug]);
        self::assertInstanceOf(Snippet::class, $snippet);
        self::assertSame('Imported snippet', $snippet->getName());
        self::assertSame(['news'], $snippet->getTagList());
        self::assertStringContainsString('Imported **body**', $snippet->getContent());
    }
}
