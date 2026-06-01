<?php

namespace Pushword\PageWorkflow\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Pushword\PageWorkflow\Entity\PageEditorialState;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Filters pages by editorial workflow state. Crosses the inverse OneToOne
 * relation Page → PageEditorialState that EasyAdmin's ChoiceFilter cannot
 * traverse on its own.
 *
 * Includes "draft" matches for pages that never had a PageEditorialState row
 * (the default before any workflow transition).
 */
final class PageWorkflowStateFilter implements FilterInterface
{
    use FilterTrait;

    /** @var list<string> */
    private const array STATES = ['draft', 'in_review', 'approved'];

    public static function new(string $propertyName, TranslatableInterface|string|false|null $label = null): self
    {
        $choices = array_combine(
            array_map(static fn (string $s): string => 'adminPageWorkflowTransition.'.$s, self::STATES),
            self::STATES,
        );

        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceFilterType::class)
            ->setFormTypeOption('translation_domain', 'messages')
            ->setFormTypeOption('value_type_options.choices', $choices)
            ->setFormTypeOption('value_type_options.multiple', true)
            ->setFormTypeOption('value_type_options.expanded', false);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (! is_array($value) || [] === $value) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $stateAlias = 'pw_workflow_state_'.spl_object_id($queryBuilder);
        $valuesParam = 'pw_workflow_state_values_'.spl_object_id($queryBuilder);

        $queryBuilder->leftJoin(PageEditorialState::class, $stateAlias, 'WITH', $stateAlias.'.page = '.$alias);

        $expr = $queryBuilder->expr();
        $hasDraft = in_array('draft', $value, true);
        $clauses = [];

        // Pages without an editorial state are treated as 'draft' (the default).
        if ($hasDraft) {
            $clauses[] = $expr->isNull($stateAlias.'.id');
        }

        $clauses[] = $expr->in($stateAlias.'.workflowState', ':'.$valuesParam);

        $queryBuilder
            ->andWhere($expr->orX(...$clauses))
            ->setParameter($valuesParam, $value);
    }
}
