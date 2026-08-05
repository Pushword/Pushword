<?php

namespace Pushword\Search\Tests;

use Doctrine\DBAL\Exception\DriverException;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Search\Service\IndexManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class IndexManagerTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private string $varDir = '';

    private string|false $previousVarDir = false;

    protected function setUp(): void
    {
        self::bootKernel();

        // IndexManager resolves its index dir from this env var, so pointing it at
        // a scratch dir keeps the worker's own index out of reach of the damage
        // this test inflicts.
        $this->varDir = sys_get_temp_dir().'/pw-index-manager-'.uniqid();
        $this->previousVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        putenv('PUSHWORD_TEST_VAR_DIR='.$this->varDir);
    }

    protected function tearDown(): void
    {
        putenv(false === $this->previousVarDir ? 'PUSHWORD_TEST_VAR_DIR' : 'PUSHWORD_TEST_VAR_DIR='.$this->previousVarDir);
        new Filesystem()->remove($this->varDir);

        parent::tearDown();
    }

    /**
     * Loupe runs SQLite with `synchronous = OFF`, so a writer killed mid-checkpoint
     * leaves `loupe.db` unreadable. That used to fail every later open for good —
     * and since the static build indexes on post-generate, it took the whole build
     * down with it, long after whatever caused the damage.
     */
    public function testAnUnreadableIndexIsResetRatherThanFailingForGood(): void
    {
        $this->overwrite($this->buildIndexFile(1), 0, 32);

        // A new manager stands in for the next process to open that file.
        $reopened = $this->indexManager();
        $reopened->replaceAll(self::HOST, $this->documents(1));

        self::assertSame(1, $reopened->getLoupe(self::HOST)->countDocuments());
    }

    /**
     * The same interrupted write can tear pages in the middle of the file while
     * leaving its head — and the small `info` table the open reads — intact. The
     * open then succeeds and SQLite only trips over the damage once the rebuild
     * walks those pages, so the reset has to cover the write as well.
     */
    public function testAnIndexDamagedPastItsHeadIsRebuiltRatherThanFailingForGood(): void
    {
        $indexFile = $this->buildIndexFile(20);

        $size = filesize($indexFile);
        self::assertNotFalse($size);
        // Halfway in: past the head, inside the pages holding the documents.
        $this->overwrite($indexFile, intdiv($size, 2), intdiv($size, 4));

        $reopened = $this->indexManager();
        $reopened->replaceAll(self::HOST, $this->documents(3));

        // Read the rebuilt index through a manager of its own: the documents have
        // to be in the file the static export copies, not merely reachable through
        // the handle that wrote them — one held open on the dropped file would
        // answer just as well.
        self::assertSame(3, $this->indexManager()->getLoupe(self::HOST)->countDocuments());
    }

    /**
     * Dropping the index is the remedy for a file SQLite cannot read at all. Any
     * other driver error — a permission, a lock, a full disk — says nothing about
     * the contents, so it has to surface with the index left where it is.
     */
    public function testADriverErrorThatIsNotCorruptionLeavesTheIndexAlone(): void
    {
        $indexManager = $this->indexManager();

        // A directory where `loupe.db` belongs: SQLite cannot open it (14), which
        // is not one of the codes meaning the contents are unreadable.
        $indexFile = $indexManager->getIndexFile(self::HOST);
        new Filesystem()->mkdir($indexFile);

        try {
            $indexManager->replaceAll(self::HOST, $this->documents(1));
            self::fail('The driver error should have surfaced.');
        } catch (DriverException $driverException) {
            self::assertSame(14, $driverException->getCode());
        }

        self::assertDirectoryExists($indexFile, 'The index was destroyed over an error that was not corruption.');
    }

    public function testReplaceAllWithoutDocumentsEmptiesTheIndex(): void
    {
        $indexManager = $this->indexManager();
        $indexManager->replaceAll(self::HOST, $this->documents(3));

        $indexManager->replaceAll(self::HOST, []);

        self::assertSame(0, $indexManager->getLoupe(self::HOST)->countDocuments());
    }

    /**
     * The static export copies `loupe.db` on its own, so the write-ahead log has
     * to be folded in first.
     */
    public function testCheckpointLeavesTheIndexFileSelfContained(): void
    {
        $indexManager = $this->indexManager();
        $indexManager->replaceAll(self::HOST, [$this->document()]);

        $indexFile = $indexManager->getIndexFile(self::HOST);
        $indexManager->checkpoint(self::HOST);

        $copy = $this->varDir.'/copied-loupe.db';
        new Filesystem()->copy($indexFile, $copy, true);

        $statement = new PDO('sqlite:'.$copy)->query('SELECT COUNT(*) FROM documents');
        self::assertNotFalse($statement);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testCheckpointIgnoresAHostThatHasNoIndexYet(): void
    {
        $this->indexManager()->checkpoint(self::HOST);

        self::assertFalse($this->indexManager()->exists(self::HOST));
    }

    /**
     * Build an index and leave it as a fresh process would find it: the log folded
     * back in and the sidecars gone, so the damage a test inflicts is all there is
     * left to go on.
     *
     * @return string the index file, ready to be damaged
     */
    private function buildIndexFile(int $documents): string
    {
        $indexManager = $this->indexManager();
        $indexManager->replaceAll(self::HOST, $this->documents($documents));
        $indexManager->checkpoint(self::HOST);

        $indexFile = $indexManager->getIndexFile(self::HOST);
        self::assertFileExists($indexFile);
        new Filesystem()->remove([$indexFile.'-wal', $indexFile.'-shm']);

        return $indexFile;
    }

    /** Overwrite bytes in place, the way an interrupted write leaves them. */
    private function overwrite(string $indexFile, int $offset, int $length): void
    {
        $handle = fopen($indexFile, 'r+');
        self::assertNotFalse($handle);
        fseek($handle, $offset);
        fwrite($handle, str_repeat('X', $length));
        fclose($handle);
    }

    /** @return list<array<string, mixed>> */
    private function documents(int $count): array
    {
        return array_map($this->document(...), range(1, $count));
    }

    /**
     * The content is padded so that a handful of documents already spread the
     * index over enough SQLite pages to have an interior to damage.
     *
     * @return array<string, mixed>
     */
    private function document(int $id = 1): array
    {
        return [
            'id' => $id,
            'title' => 'Zylphor '.$id,
            'h1' => 'Zylphor '.$id,
            'url' => 'https://'.self::HOST.'/zylphor-'.$id,
            'slug' => 'zylphor-'.$id,
            'host' => self::HOST,
            'locale' => 'en',
            'tags' => [],
            'content' => str_repeat('zylphor content lorem ipsum dolor sit amet '.$id.' ', 20),
        ];
    }

    /**
     * A real instance rather than the container's: each one reads the index dir
     * afresh, which is what makes it stand in for a separate process.
     */
    private function indexManager(): IndexManager
    {
        $container = self::getContainer();

        /** @var list<string> $searchable */
        $searchable = $container->getParameter('pw.search.searchable_attributes');
        /** @var list<string> $filterable */
        $filterable = $container->getParameter('pw.search.filterable_attributes');

        return new IndexManager(
            $container->get(SiteRegistry::class),
            $this->varDir.'/search',
            $searchable,
            $filterable,
        );
    }
}
