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
        $this->snippetsDir = $container->get(FlatFileContentDirFinder::class)->get('localhost.dev').'/pw-snippets';
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

    public function testExportWritesGlobalSnippetToBaseSnippetsDir(): void
    {
        $globalDir = self::getContainer()->get(FlatFileContentDirFinder::class)->getBaseDir().'/pw-snippets';

        $globalSlug = 'global-export-'.uniqid();
        $snippet = new Snippet();
        $snippet->host = ''; // "All hosts"
        $snippet->setSlug($globalSlug);
        $snippet->setName('Global Export');
        $snippet->setContent('# Global body');

        $this->em->persist($snippet);
        $this->em->flush();

        $path = $globalDir.'/'.$globalSlug.'.md';

        try {
            // localhost.dev is the default app's primary host, so the global pass runs.
            $this->sync->export('localhost.dev', entity: 'snippet');

            self::assertFileExists($path, 'a global snippet must be exported outside any host folder');
            self::assertStringContainsString('# Global body', (string) file_get_contents($path));
        } finally {
            new Filesystem()->remove($path);
            $this->em->remove($snippet);
            $this->em->flush();
        }
    }

    public function testImportCreatesGlobalSnippetFromBaseSnippetsDir(): void
    {
        $globalDir = self::getContainer()->get(FlatFileContentDirFinder::class)->getBaseDir().'/pw-snippets';

        $globalSlug = 'global-import-'.uniqid();
        $path = $globalDir.'/'.$globalSlug.'.md';
        new Filesystem()->dumpFile($path, "---\nname: Global Import\n---\n\nglobal content\n");

        try {
            $this->sync->import('localhost.dev', entity: 'snippet');

            $snippet = $this->em->getRepository(Snippet::class)->findOneBy(['slug' => $globalSlug]);
            self::assertInstanceOf(Snippet::class, $snippet);
            self::assertSame('', $snippet->host, 'a snippet under the base snippets dir is global (host = "")');
            self::assertSame('Global Import', $snippet->getName());
        } finally {
            new Filesystem()->remove($path);
            $snippet = $this->em->getRepository(Snippet::class)->findOneBy(['slug' => $globalSlug]);
            if ($snippet instanceof Snippet) {
                $this->em->remove($snippet);
                $this->em->flush();
            }
        }
    }

    public function testGlobalSnippetsAreNotTouchedByNonPrimaryHostPass(): void
    {
        $globalDir = self::getContainer()->get(FlatFileContentDirFinder::class)->getBaseDir().'/pw-snippets';

        $globalSlug = 'global-guard-'.uniqid();
        $snippet = new Snippet();
        $snippet->host = ''; // "All hosts"
        $snippet->setSlug($globalSlug);
        $snippet->setName('Global Guard');
        $snippet->setContent('global');

        $this->em->persist($snippet);
        $this->em->flush();

        $path = $globalDir.'/'.$globalSlug.'.md';

        try {
            // pushword.piedweb.com is NOT the default app's primary host, so the
            // global directory must be left untouched (synced once, on the primary pass).
            $this->sync->export('pushword.piedweb.com', entity: 'snippet');

            self::assertFileDoesNotExist($path, 'a non-primary host pass must not export global snippets');
        } finally {
            new Filesystem()->remove($path);
            $this->em->remove($snippet);
            $this->em->flush();
        }
    }

    public function testImportDeletesGlobalSnippetWhoseFileWasRemoved(): void
    {
        $globalDir = self::getContainer()->get(FlatFileContentDirFinder::class)->getBaseDir().'/pw-snippets';

        // A: present as a global file, must survive the import.
        $keepSlug = 'global-keep-'.uniqid();
        $keepPath = $globalDir.'/'.$keepSlug.'.md';
        new Filesystem()->dumpFile($keepPath, "---\nname: Keep\n---\nkeep\n");

        // B: a global snippet in the database only (its file was removed), must be deleted.
        $orphanSlug = 'global-orphan-'.uniqid();
        $orphan = new Snippet();
        $orphan->host = '';
        $orphan->setSlug($orphanSlug);
        $orphan->setName('Orphan');
        $orphan->setContent('gone');

        $this->em->persist($orphan);
        $this->em->flush();

        try {
            $this->sync->import('localhost.dev', entity: 'snippet');

            $repo = $this->em->getRepository(Snippet::class);
            self::assertNull($repo->findOneBy(['slug' => $orphanSlug, 'host' => '']), 'global snippet with no file should be deleted');
            self::assertInstanceOf(Snippet::class, $repo->findOneBy(['slug' => $keepSlug, 'host' => '']), 'global snippet with a file should be kept');
        } finally {
            new Filesystem()->remove($keepPath);
            foreach ([$keepSlug, $orphanSlug] as $slug) {
                $found = $this->em->getRepository(Snippet::class)->findOneBy(['slug' => $slug]);
                if ($found instanceof Snippet) {
                    $this->em->remove($found);
                }
            }

            $this->em->flush();
        }
    }
}
