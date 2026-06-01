<?php

namespace Pushword\PageWorkflow\Tests\Filter;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Filter\PageWorkflowStateFilter;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Builds a real Doctrine QueryBuilder against the page entity and inspects the
 * generated DQL: ensures the OneToOne is left-joined, the workflowState IN clause
 * is emitted, and missing-row pages are treated as 'draft' when that value is
 * selected.
 */
#[Group('integration')]
final class PageWorkflowStateFilterTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');
    }

    public function testEmptyValueLeavesQueryBuilderUntouched(): void
    {
        $qb = $this->em->createQueryBuilder()->select('p')->from(Page::class, 'p');
        $before = $qb->getDQL();

        $filter = PageWorkflowStateFilter::new('editorialState.workflowState');
        $filter->apply($qb, $this->dto([]), null, $this->entityDto());

        self::assertSame($before, $qb->getDQL(), 'Empty filter value should be a no-op');
        self::assertSame([], $qb->getParameters()->toArray());
    }

    public function testInReviewOnlyJoinsAndAddsInClauseWithoutNullBranch(): void
    {
        $qb = $this->em->createQueryBuilder()->select('p')->from(Page::class, 'p');

        $filter = PageWorkflowStateFilter::new('editorialState.workflowState');
        $filter->apply($qb, $this->dto(['in_review']), null, $this->entityDto());

        $dql = $qb->getDQL();
        self::assertStringContainsString('LEFT JOIN Pushword\PageWorkflow\Entity\PageEditorialState', $dql);
        self::assertStringContainsString('.workflowState IN(', $dql);
        self::assertStringNotContainsString('.id IS NULL', $dql, 'No null branch when "draft" is not selected');

        $params = $qb->getParameters();
        self::assertCount(1, $params);
        self::assertSame(['in_review'], $params->first()->getValue());
    }

    public function testDraftSelectionAddsNullBranch(): void
    {
        $qb = $this->em->createQueryBuilder()->select('p')->from(Page::class, 'p');

        $filter = PageWorkflowStateFilter::new('editorialState.workflowState');
        $filter->apply($qb, $this->dto(['draft', 'approved']), null, $this->entityDto());

        $dql = $qb->getDQL();
        self::assertStringContainsString('.id IS NULL', $dql, 'Draft selection must capture pages without a state row');
        self::assertStringContainsString('.workflowState IN(', $dql);
    }

    /**
     * @param list<string> $value
     */
    private function dto(array $value): FilterDataDto
    {
        // FilterDataDto is final and the factory wants a fully wired FilterDto;
        // reflection lets us inject just the two fields the filter reads.
        $dto = new ReflectionClass(FilterDataDto::class)->newInstanceWithoutConstructor();
        $value_ = new ReflectionProperty(FilterDataDto::class, 'value');
        $value_->setValue($dto, $value);

        $alias = new ReflectionProperty(FilterDataDto::class, 'entityAlias');
        $alias->setValue($dto, 'p');

        return $dto;
    }

    private function entityDto(): EntityDto
    {
        return new ReflectionClass(EntityDto::class)->newInstanceWithoutConstructor();
    }
}
