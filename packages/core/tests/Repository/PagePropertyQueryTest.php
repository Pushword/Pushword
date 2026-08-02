<?php

namespace Pushword\Core\Tests\Repository;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * `prop.*` typed comparison and ordering, against the dev-app schema where
 * localhost.dev declares priority as int. The 9-vs-10 fixtures are the
 * platform probe: MySQL/MariaDB without the JSON_NUMBER cast sorts them
 * lexically (10 before 9).
 */
#[Group('integration')]
final class PagePropertyQueryTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string PREFIX = 'prop-query-fixture-';

    private EntityManagerInterface $entityManager;

    private PageRepository $pageRepository;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->pageRepository = self::getContainer()->get(PageRepository::class);

        foreach ([['a', 9], ['b', 10], ['c', 2], ['d', null]] as [$suffix, $priority]) {
            $page = new Page();
            $page->host = self::HOST;
            $page->locale = 'en';
            $page->slug = self::PREFIX.$suffix;
            $page->h1 = 'Prop query fixture '.$suffix;
            $page->setMainContent('Content');
            $page->publishedAt = new DateTime('-1 day');
            if (null !== $priority) {
                $page->setCustomProperty('priority', $priority);
            }

            $this->entityManager->persist($page);
        }

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->entityManager->getRepository(Page::class)->findBy(['host' => self::HOST]) as $page) {
            if (str_starts_with($page->slug, self::PREFIX)) {
                $this->entityManager->remove($page);
            }
        }

        $this->entityManager->flush();
        parent::tearDown();
    }

    public function testDeclaredIntComparesNumerically(): void
    {
        $slugs = $this->slugs($this->pages([['prop.priority', '>', 2]]));

        self::assertSame([self::PREFIX.'a', self::PREFIX.'b'], $slugs);
    }

    public function testComparisonCoercesANumericStringValue(): void
    {
        $slugs = $this->slugs($this->pages([['prop.priority', '>=', '9']]));

        self::assertSame([self::PREFIX.'a', self::PREFIX.'b'], $slugs);
    }

    public function testOrderByPropSortsNumericallyWithNullsLast(): void
    {
        $slugs = $this->slugs($this->pages([['slug', 'LIKE', self::PREFIX.'%']], ['prop.priority ASC']));

        // 2, 9, 10 — lexical order would put 10 before 9 — and the page
        // without the property comes last, not first.
        self::assertSame([self::PREFIX.'c', self::PREFIX.'a', self::PREFIX.'b', self::PREFIX.'d'], $slugs);
    }

    public function testAMultiKeyOrderToleratesTheSpaceAfterTheComma(): void
    {
        // `weight DESC, publishedAt DESC` is how these are written. Unsplit,
        // the second key kept the space that followed the comma and was read
        // as an empty key ordered `publishedAt DESC` — tolerated by Doctrine's
        // lexer until the direction was validated, then a hard throw.
        $slugs = $this->slugs($this->pages([['slug', 'LIKE', self::PREFIX.'%']], ['slug ASC, publishedAt DESC']));

        self::assertSame([self::PREFIX.'a', self::PREFIX.'b', self::PREFIX.'c', self::PREFIX.'d'], $slugs);
    }

    public function testComparisonOnUndeclaredPropertyFailsClean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('page_properties');

        $this->pages([['prop.undeclaredThing', '>', 2]]);
    }

    public function testComparisonWithANonNumericValueFailsClean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('needs a numeric value');

        $this->pages([['prop.priority', '>', 'abc']]);
    }

    public function testHostileKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid property name');

        $this->pages([["prop.a') OR (1=1", '=', 'x']]);
    }

    public function testHostileOrderDirectionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pages([], ['prop.priority ASC, (SELECT 1)']);
    }

    /**
     * @param array<mixed>  $where
     * @param array<string> $orderBy
     *
     * @return Page[]
     */
    private function pages(array $where, array $orderBy = ['slug ASC']): array
    {
        /** @var Page[] $pages */
        $pages = $this->pageRepository->getPublishedPages(self::HOST, $where, $orderBy);

        return array_values(array_filter($pages, static fn (Page $page): bool => str_starts_with($page->slug, self::PREFIX)));
    }

    /**
     * @param Page[] $pages
     *
     * @return string[]
     */
    private function slugs(array $pages): array
    {
        return array_map(static fn (Page $page): string => $page->slug, $pages);
    }
}
