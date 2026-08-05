<?php

namespace Pushword\Search\Tests;

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
        $indexManager = $this->indexManager();
        $indexManager->replaceAll(self::HOST, [$this->document()]);
        // Fold the WAL in and drop the sidecars, so the damage below is what a
        // fresh process actually finds: no log left to recover the header from.
        $indexManager->checkpoint(self::HOST);

        $indexFile = $indexManager->getIndexFile(self::HOST);
        self::assertFileExists($indexFile);
        new Filesystem()->remove([$indexFile.'-wal', $indexFile.'-shm']);

        $handle = fopen($indexFile, 'r+');
        self::assertNotFalse($handle);
        fwrite($handle, str_repeat('X', 32));
        fclose($handle);

        // A new manager stands in for the next process to open that file.
        $reopened = $this->indexManager();
        $reopened->replaceAll(self::HOST, [$this->document()]);

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
        $indexManager = $this->indexManager();
        $indexManager->replaceAll(self::HOST, $this->documents(20));
        $indexManager->checkpoint(self::HOST);

        $indexFile = $indexManager->getIndexFile(self::HOST);
        new Filesystem()->remove([$indexFile.'-wal', $indexFile.'-shm']);

        $size = filesize($indexFile);
        self::assertNotFalse($size);
        $handle = fopen($indexFile, 'r+');
        self::assertNotFalse($handle);
        // Halfway in: past the head, inside the pages holding the documents.
        fseek($handle, intdiv($size, 2));
        fwrite($handle, str_repeat('X', intdiv($size, 4)));
        fclose($handle);

        $reopened = $this->indexManager();
        $reopened->replaceAll(self::HOST, $this->documents(3));

        self::assertSame(3, $reopened->getLoupe(self::HOST)->countDocuments());
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
