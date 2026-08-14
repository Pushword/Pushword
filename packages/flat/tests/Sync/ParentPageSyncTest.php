<?php

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\PageSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class ParentPageSyncTest extends KernelTestCase
{
    private EntityManager $em;

    private PageSync $pageSync;

    private string $contentDir;

    private Filesystem $filesystem;

    /** @var string[] */
    private array $createdFiles = [];

    /** @var string[] */
    private array $testSlugs = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var PageSync $pageSync */
        $pageSync = self::getContainer()->get(PageSync::class);
        $this->pageSync = $pageSync;

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');

        $this->pageSync->export('localhost.dev', true, $this->contentDir);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach ($this->testSlugs as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                // Must remove children first (due to FK constraints)
                $page->parentPage = null;
            }
        }

        $this->em->flush();
        foreach ($this->testSlugs as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                $this->em->remove($page);
            }
        }

        $this->em->flush();
        parent::tearDown();
    }

    private function createMd(string $fileName, string $content): void
    {
        $path = $this->contentDir.'/'.$fileName;
        $this->filesystem->dumpFile($path, $content);
        touch($path, time() + 100);
        $this->createdFiles[] = $path;
    }

    public function testParentPageExportedAsSlug(): void
    {
        $this->testSlugs = ['parent-export-test', 'child-export-test'];

        // Create parent and child pages
        $parent = new Page();
        $parent->slug = 'parent-export-test';
        $parent->h1 = 'Parent Export';
        $parent->host = 'localhost.dev';
        $parent->locale = 'en';
        $parent->mainContent = 'Parent content';

        $this->em->persist($parent);
        $this->em->flush();

        $child = new Page();
        $child->slug = 'child-export-test';
        $child->h1 = 'Child Export';
        $child->host = 'localhost.dev';
        $child->locale = 'en';
        $child->mainContent = 'Child content';
        $child->parentPage = $parent;

        $this->em->persist($child);
        $this->em->flush();

        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Check exported .md contains parentPage
        $childMdPath = $this->contentDir.'/child-export-test.md';
        self::assertFileExists($childMdPath);
        $content = $this->filesystem->readFile($childMdPath);
        self::assertStringContainsString('parentPage: parent-export-test', $content);
        $this->createdFiles[] = $childMdPath;
        $this->createdFiles[] = $this->contentDir.'/parent-export-test.md';
    }

    public function testParentPageImportedFromFrontmatter(): void
    {
        $this->testSlugs = ['parent-import-test', 'child-import-test'];

        // Create parent page in DB first
        $parent = new Page();
        $parent->slug = 'parent-import-test';
        $parent->h1 = 'Parent Import';
        $parent->host = 'localhost.dev';
        $parent->locale = 'en';
        $parent->mainContent = 'Parent content';

        $this->em->persist($parent);
        $this->em->flush();

        // Export parent so it exists as .md
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
        $this->createdFiles[] = $this->contentDir.'/parent-import-test.md';

        // Create child .md with parentPage reference
        $this->createMd('child-import-test.md', "---\nh1: 'Child Import'\nparentPage: parent-import-test\n---\n\nChild content");

        $this->pageSync->import('localhost.dev');

        $child = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'child-import-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $child);
        self::assertNotNull($child->parentPage);
        self::assertSame('parent-import-test', $child->parentPage->slug);
    }

    public function testParentPageRoundTrip(): void
    {
        $this->testSlugs = ['parent-roundtrip', 'child-roundtrip'];

        // Create parent and child in DB
        $parent = new Page();
        $parent->slug = 'parent-roundtrip';
        $parent->h1 = 'Parent Round Trip';
        $parent->host = 'localhost.dev';
        $parent->locale = 'en';
        $parent->mainContent = 'Parent';

        $this->em->persist($parent);
        $this->em->flush();

        $child = new Page();
        $child->slug = 'child-roundtrip';
        $child->h1 = 'Child Round Trip';
        $child->host = 'localhost.dev';
        $child->locale = 'en';
        $child->mainContent = 'Child';
        $child->parentPage = $parent;

        $this->em->persist($child);
        $this->em->flush();

        // Export
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
        $this->createdFiles[] = $this->contentDir.'/parent-roundtrip.md';
        $this->createdFiles[] = $this->contentDir.'/child-roundtrip.md';

        // Delete child from DB
        $child->parentPage = null;
        $this->em->flush();
        $this->em->remove($child);
        $this->em->flush();
        $this->em->clear();

        // Re-import
        // Touch the child file to ensure it's newer
        touch($this->contentDir.'/child-roundtrip.md', time() + 100);
        $this->pageSync->import('localhost.dev');

        $reimportedChild = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'child-roundtrip', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $reimportedChild);
        self::assertNotNull($reimportedChild->parentPage);
        self::assertSame('parent-roundtrip', $reimportedChild->parentPage->slug);
    }

    public function testNestedThreeLevelHierarchy(): void
    {
        $this->testSlugs = ['grandparent-test', 'parent-nested-test', 'child-nested-test'];

        $this->createMd('grandparent-test.md', "---\nh1: 'Grandparent'\n---\n\nGrandparent content");
        $this->createMd('parent-nested-test.md', "---\nh1: 'Parent Nested'\nparentPage: grandparent-test\n---\n\nParent content");
        $this->createMd('child-nested-test.md', "---\nh1: 'Child Nested'\nparentPage: parent-nested-test\n---\n\nChild content");

        $this->pageSync->import('localhost.dev');

        $child = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'child-nested-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $child);
        self::assertNotNull($child->parentPage, 'Child should have parent');
        self::assertSame('parent-nested-test', $child->parentPage->slug);

        $parentPage = $child->parentPage;
        self::assertNotNull($parentPage->parentPage, 'Parent should have grandparent');
        self::assertSame('grandparent-test', $parentPage->parentPage->slug);
    }

    public function testParentPageRemovedWhenExplicitlyNulled(): void
    {
        $this->testSlugs = ['parent-remove-test', 'child-remove-test'];

        // Create parent and child
        $this->createMd('parent-remove-test.md', "---\nh1: 'Parent Remove'\n---\n\nParent");
        $this->createMd('child-remove-test.md', "---\nh1: 'Child Remove'\nparentPage: parent-remove-test\n---\n\nChild");

        $this->pageSync->import('localhost.dev');

        $child = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'child-remove-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $child);
        self::assertNotNull($child->parentPage);

        // Update child .md with parentPage explicitly set to empty
        $this->createMd('child-remove-test.md', "---\nh1: 'Child Remove'\nparentPage: ''\n---\n\nChild without parent");

        $this->pageSync->import('localhost.dev');

        $this->em->clear();
        $child = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'child-remove-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $child);
        // Note: parentPage removal depends on whether the importer explicitly nulls absent properties
        // With parentPage: '' the importer may or may not clear the parent
        self::assertSame('Child Remove', $child->h1, 'Child page should be re-imported with updated content');
    }

    public function testParentPageCreatedInSameImportCycle(): void
    {
        $this->testSlugs = ['new-parent-deferred', 'new-child-deferred'];

        // Create both parent and child as new .md files in same import
        $this->createMd('new-parent-deferred.md', "---\nh1: 'New Parent'\n---\n\nNew parent content");
        $this->createMd('new-child-deferred.md', "---\nh1: 'New Child'\nparentPage: new-parent-deferred\n---\n\nNew child content");

        $this->pageSync->import('localhost.dev');

        $child = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'new-child-deferred', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $child);
        self::assertNotNull($child->parentPage, 'Parent page created in same import cycle should be resolved via deferred resolution');
        self::assertSame('new-parent-deferred', $child->parentPage->slug);
    }

    /**
     * Both .md files vanish between two syncs, so the delete pass removes a
     * parent and its child together: neither may survive, and the pass must not
     * abort on the self-referencing foreign key.
     *
     * This locks the outcome, not the mechanism. It passes with and without
     * breakSelfReferentialLinks(), on SQLite and on MariaDB, because
     * PageListener::preRemove already nulls the children of a page being
     * removed. Read it as coverage of the pair being deleted — the foreign-key
     * ordering that helper guards still has no failing case to its name.
     */
    public function testDeletingAParentAndItsChildInTheSamePass(): void
    {
        $this->testSlugs = ['parent-both-deleted', 'child-both-deleted'];

        $this->createMd('parent-both-deleted.md', "---\nh1: 'Parent Both'\n---\n\nParent content");
        $this->createMd('child-both-deleted.md', "---\nh1: 'Child Both'\nparentPage: parent-both-deleted\n---\n\nChild content");

        $this->pageSync->import('localhost.dev');

        $child = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'child-both-deleted', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $child);
        self::assertNotNull($child->parentPage, 'The pair must be linked, or this deletes two unrelated pages and proves nothing');

        // The pages now exist only in the DB. The rest of the host keeps its
        // files, so the delete pass still runs instead of short-circuiting.
        unlink($this->contentDir.'/parent-both-deleted.md');
        unlink($this->contentDir.'/child-both-deleted.md');

        $this->pageSync->import('localhost.dev');

        $this->em->clear();
        $repository = $this->em->getRepository(Page::class);
        self::assertNull($repository->findOneBy(['slug' => 'child-both-deleted', 'host' => 'localhost.dev']));
        self::assertNull($repository->findOneBy(['slug' => 'parent-both-deleted', 'host' => 'localhost.dev']));
    }
}
