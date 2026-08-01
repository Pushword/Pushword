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

/**
 * Regression: a frontmatter key matching a public typed property whose type the
 * raw value cannot satisfy (eg `editedBy` is ?User) must fall back to
 * customProperties instead of throwing a TypeError during import.
 */
#[Group('integration')]
final class TypedPropertyFrontmatterTest extends KernelTestCase
{
    private EntityManager $em;

    private PageSync $pageSync;

    private string $contentDir;

    private Filesystem $filesystem;

    /** @var string[] */
    private array $createdFiles = [];

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

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'typed-property-fallback', 'host' => 'localhost.dev']);
        if ($page instanceof Page) {
            $this->em->remove($page);
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testIncompatibleFrontmatterValueLandsInCustomProperties(): void
    {
        $path = $this->contentDir.'/typed-property-fallback.md';
        $this->filesystem->dumpFile($path, "---\nh1: 'Typed Property Fallback'\neditedBy: 'Robin'\n---\n\nContent");
        touch($path, time() + 100);
        $this->createdFiles[] = $path;

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'typed-property-fallback', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertNull($page->editedBy);
        self::assertSame('Robin', $page->getCustomProperty('editedBy'));
    }
}
