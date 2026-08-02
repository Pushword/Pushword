<?php

namespace Pushword\Newsletter\Criteria;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;

/**
 * A criteria language, described well enough to build an editor from.
 *
 * The admin's condition builder knows no field and no operator of its own: it
 * asks for this, so a source a bundle registered gets the same rows, the same
 * dropdowns and the same validation as the two that ship — and so a field added
 * to a language appears in the editor without anyone touching the JavaScript.
 *
 * What a language declares ({@see AbstractCriteria::FIELD_OPERATORS} and its
 * neighbours) is merged with what the site has already written
 * ({@see CriteriaSuggestions}) — the operators are the grammar, the suggestions
 * are the content, and only the first of the two is enforced.
 */
final readonly class CriteriaVocabulary
{
    /** @param Traversable<CriteriaSuggestions> $suggesters */
    public function __construct(
        #[AutowireIterator('pushword.newsletter.criteria_suggestions')]
        private Traversable $suggesters,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param class-string<AbstractCriteria> $criteria
     * @param string[]                       $hosts
     *
     * @return array<string, mixed>
     */
    public function describe(string $criteria, array $hosts): array
    {
        $suggestions = $this->suggestions($criteria, $hosts);
        $fields = [];

        foreach ($criteria::FIELD_OPERATORS as $field => $operators) {
            $fields[$field] = [
                'operators' => $this->operators($criteria, $operators),
                'suggestions' => $suggestions[$field] ?? [],
            ];
        }

        // Last, and apart: it is not a field but a family of them, and the
        // editor asks for a key once it is picked.
        $fields[AbstractCriteria::PROP_PREFIX] = [
            'property' => true,
            'operators' => $this->operators($criteria, $criteria::PROP_OPERATORS),
            'suggestions' => $suggestions[AbstractCriteria::PROP_PREFIX] ?? [],
        ];

        return [
            'acceptsSearch' => $criteria::ACCEPTS_SEARCH,
            'propertyPrefix' => AbstractCriteria::PROP_PREFIX,
            'fields' => $fields,
            'labels' => $this->labels(),
        ];
    }

    /**
     * @param class-string<AbstractCriteria> $criteria
     * @param list<string>                   $operators
     *
     * @return list<array<string, mixed>>
     */
    private function operators(string $criteria, array $operators): array
    {
        return array_map(static fn (string $operator): array => [
            'name' => $operator,
            'valueless' => \in_array($operator, AbstractCriteria::VALUELESS_OPERATORS, true),
            'duration' => \in_array($operator, $criteria::DURATION_OPERATORS, true),
        ], $operators);
    }

    /**
     * @param class-string<AbstractCriteria> $criteria
     * @param string[]                       $hosts
     *
     * @return array<string, list<string>>
     */
    private function suggestions(string $criteria, array $hosts): array
    {
        foreach ($this->suggesters as $suggester) {
            if ($suggester->criteria() !== $criteria) {
                continue;
            }

            // Deduplicated and alphabetical here rather than in each provider:
            // that a list read by an editor is ordered is this layer's business,
            // and a provider that only knows how to query should not repeat it.
            return array_map(static function (array $values): array {
                $values = array_values(array_unique($values));
                sort($values);

                return $values;
            }, $suggester->suggest($hosts));
        }

        return [];
    }

    /**
     * The builder's own words. They travel with the vocabulary rather than as
     * data attributes: the editor fetches it before rendering anything anyway,
     * and this keeps every translatable string in the bundle's catalogue.
     *
     * @return array<string, string>
     */
    private function labels(): array
    {
        $labels = [
            'addCondition', 'addGroup', 'all', 'any', 'duration', 'empty', 'field', 'operator',
            'previewContacts', 'previewFailed', 'previewNeedsAudience', 'previewSaveFirst', 'previewTrigger',
            'property', 'raw', 'rawHint', 'rawInvalid', 'remove', 'searchHint', 'sinceAll', 'toBuilder',
            'unitDays', 'unitHours', 'unitMinutes', 'unitWeeks', 'value',
        ];

        return array_combine(
            $labels,
            array_map(fn (string $label): string => $this->translator->trans('newsletter.criteria.'.$label), $labels),
        );
    }
}
