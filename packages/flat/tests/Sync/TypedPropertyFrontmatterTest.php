<?php

namespace Pushword\Flat\Tests\Sync;

use DateTime;
use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Importer\PageImporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Regression: a frontmatter key matching a public typed property whose type the
 * raw value cannot satisfy (eg `editedBy` is ?User) must fall back to
 * customProperties instead of throwing a TypeError during import.
 *
 * Imports the single file through PageImporter directly — the full PageSync
 * pipeline would sweep sibling fixture pages via deleteMissingPages().
 */
#[Group('integration')]
final class TypedPropertyFrontmatterTest extends KernelTestCase
{
    private EntityManager $em;

    private string $mdPath = '';

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;
    }

    protected function tearDown(): void
    {
        if ('' !== $this->mdPath) {
            @unlink($this->mdPath);
        }

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'typed-property-fallback', 'host' => 'localhost.dev']);
        if ($page instanceof Page) {
            $this->em->remove($page);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testIncompatibleFrontmatterValueLandsInCustomProperties(): void
    {
        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->mdPath = $contentDirFinder->get('localhost.dev').'/typed-property-fallback.md';

        new Filesystem()->dumpFile($this->mdPath, "---\nh1: 'Typed Property Fallback'\neditedBy: 'Robin'\n---\n\nContent");

        /** @var PageImporter $pageImporter */
        $pageImporter = self::getContainer()->get(PageImporter::class);
        $pageImporter->import($this->mdPath, new DateTime('+1 minute'));
        $pageImporter->finishImport();

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'typed-property-fallback', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertNull($page->editedBy);
        self::assertSame('Robin', $page->getCustomProperty('editedBy'));
    }
}
