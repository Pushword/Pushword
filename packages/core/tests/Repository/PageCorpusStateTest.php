<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageCorpusStateTest extends KernelTestCase
{
    use QueryCountingTrait;

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        parent::tearDown();
    }

    #[DataProvider('hosts')]
    public function testFingerprintMatchesPublishedPagesWithoutJoiningRelations(string $host): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(PageRepository::class);
        $expected = ['pages' => 0, 'lastEditAt' => null];
        foreach ($repository->getPublishedPages($host) as $page) {
            ++$expected['pages'];
            if (null !== $page->updatedAt) {
                $expected['lastEditAt'] = max($expected['lastEditAt'] ?? \PHP_INT_MIN, $page->updatedAt->getTimestamp());
            }
        }

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $this->startCountingQueries($connection);
        self::assertSame($expected, $repository->getPublishedCorpusState($host));
        self::assertCount(1, $this->queryCounter->queries);
        self::assertStringNotContainsString(' JOIN ', strtoupper($this->queryCounter->queries[0]));
    }

    /** @return iterable<string, array{string}> */
    public static function hosts(): iterable
    {
        yield 'populated' => ['localhost.dev'];
        yield 'empty' => ['missing-corpus.test'];
        yield 'all hosts' => [''];
    }

    public function testCorpusFingerprintHasACoveringIndex(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $indexes = $connection->createSchemaManager()->listTableIndexes('page');
        self::assertArrayHasKey('idx_page_corpus_state', $indexes);
        self::assertSame(['host', 'id', 'published_at', 'slug', 'updated_at'], $indexes['idx_page_corpus_state']->getColumns());
    }
}
