<?php

namespace Pushword\Newsletter\Criteria;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\Repository\PageRepository;
use Pushword\Newsletter\Content\PageCriteria;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What the site already offers a page rule: its tags, its templates, and the
 * slugs that name a section.
 */
#[AutoconfigureTag('pushword.newsletter.criteria_suggestions')]
final readonly class PageCriteriaSuggestions implements CriteriaSuggestions
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageRepository $pageRepository,
        private PagePropertySchemaRegistry $schemaRegistry,
    ) {
    }

    public function criteria(): string
    {
        return PageCriteria::class;
    }

    public function suggest(array $hosts): array
    {
        // The slugs that already have a page under them: exactly what `parent`
        // and `ancestor` take, and the only slugs few enough to be worth
        // listing — a whole site's would be a scroll, not a suggestion.
        $sections = $this->sections($hosts);

        return [
            'tag' => $this->sorted($this->pageRepository->getAllTags([] !== $hosts ? $hosts : null)),
            'template' => $this->templates($hosts),
            'parent' => $sections,
            'ancestor' => $sections,
            'slug' => $sections,
            AbstractCriteria::PROP_PREFIX => $this->propertyKeys($hosts),
        ];
    }

    /**
     * @param string[] $hosts
     *
     * @return list<string>
     */
    private function sections(array $hosts): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT parent.slug')
            ->from(Page::class, 'p')
            ->innerJoin('p.parentPage', 'parent');

        if ([] !== $hosts) {
            $queryBuilder->andWhere('p.host IN (:hosts)')->setParameter('hosts', $hosts);
        }

        return $this->column($queryBuilder->getQuery()->getResult(), 'slug');
    }

    /**
     * @param string[] $hosts
     *
     * @return list<string>
     */
    private function templates(array $hosts): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT p.template')
            ->from(Page::class, 'p')
            ->andWhere('p.template IS NOT NULL')
            ->andWhere("p.template != ''");

        if ([] !== $hosts) {
            $queryBuilder->andWhere('p.host IN (:hosts)')->setParameter('hosts', $hosts);
        }

        return $this->column($queryBuilder->getQuery()->getResult(), 'template');
    }

    /**
     * Declared properties, not stored ones: `page_properties` is what a site
     * says a page may carry, and a rule filtering on anything else compares
     * strings it has no schema for.
     *
     * @param string[] $hosts
     *
     * @return list<string>
     */
    private function propertyKeys(array $hosts): array
    {
        $keys = [];

        foreach ([] !== $hosts ? $hosts : [null] as $host) {
            $keys = [...$keys, ...array_keys($this->schemaRegistry->for($host))];
        }

        return $this->sorted($keys);
    }

    /**
     * @param mixed[] $rows
     *
     * @return list<string>
     */
    private function column(array $rows, string $key): array
    {
        /** @var list<string> $values */
        $values = array_column($rows, $key);

        return $this->sorted($values);
    }

    /**
     * @param string[] $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
