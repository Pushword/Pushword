<?php

namespace Pushword\Core\Query;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\Query\Field\FieldRegistry;
use Pushword\Core\Query\Field\FieldStrategy;
use Pushword\Core\Query\Field\Strategy\AncestorStrategy;
use Pushword\Core\Query\Field\Strategy\ColumnStrategy;
use Pushword\Core\Query\Field\Strategy\JoinedColumnStrategy;
use Pushword\Core\Query\Field\Strategy\JsonArrayStrategy;
use Pushword\Core\Query\Field\Strategy\JsonPropertyStrategy;
use Pushword\Core\Query\Field\Strategy\OptionalColumnStrategy;
use Pushword\Core\Query\Field\Strategy\TextStrategy;

/**
 * What is filterable on a `Page`.
 *
 * The one place to read to know what a search can say, and the one place to
 * change to teach it a new field — both surfaces ask this, so neither can drift
 * from the other.
 */
final class PageFieldRegistry implements FieldRegistry
{
    /**
     * Resolved while parsing a `pages_list` search, where the page being
     * rendered is known — a `related` means the sisters of *that* page. A rule
     * stored in the database has no such page, which is why these are named
     * rather than left to fail as unknown fields.
     */
    public const array CONTEXTUAL_FIELDS = [
        'children', 'sisters', 'parent_children', 'grandchildren', 'children_children', 'related',
    ];

    /** @var array<string, FieldStrategy>|null */
    private ?array $strategies = null;

    /**
     * Null schema registry (the deprecated FilterWhereParser path, which has no
     * service context) keeps every `prop.*` non-comparable — the pre-schema
     * behavior.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?PagePropertySchemaRegistry $schemaRegistry = null,
    ) {
    }

    public function strategy(string $field): ?FieldStrategy
    {
        if (str_starts_with($field, JsonPropertyStrategy::PREFIX)) {
            // `prop.` alone names no property.
            if (JsonPropertyStrategy::PREFIX === $field) {
                return null;
            }

            $schema = $this->schemaRegistry?->schema(null, substr($field, \strlen(JsonPropertyStrategy::PREFIX)));

            return new JsonPropertyStrategy('customProperties', $schema?->type->isComparable() ?? false);
        }

        return $this->strategies()[$field] ?? null;
    }

    public function fields(): array
    {
        return [...array_keys($this->strategies()), JsonPropertyStrategy::PREFIX.'<key>'];
    }

    public function contextualFields(): array
    {
        return self::CONTEXTUAL_FIELDS;
    }

    /** @return array<string, FieldStrategy> */
    private function strategies(): array
    {
        return $this->strategies ??= [
            'slug' => new TextStrategy('slug'),
            'h1' => new TextStrategy('h1'),
            'title' => new TextStrategy('title'),
            'mainContent' => new TextStrategy('mainContent'),

            'locale' => new ColumnStrategy('locale'),
            'host' => new ColumnStrategy('host'),
            'id' => new ColumnStrategy('id', ['=', '!=', '<>', '<', '>', '<=', '>=', 'IN']),
            'parentPage' => new ColumnStrategy('parentPage', ['=', '!=', 'IN']),
            'publishedAt' => new ColumnStrategy('publishedAt', ['<', '>', '<=', '>=']),
            'createdAt' => new ColumnStrategy('createdAt', ['<', '>', '<=', '>=']),
            'updatedAt' => new ColumnStrategy('updatedAt', ['<', '>', '<=', '>=']),

            // An absent template is the site's default one: a known value, and
            // genuinely not the one being excluded.
            'template' => new OptionalColumnStrategy('template'),
            'parent' => new JoinedColumnStrategy('parentPage', 'parent', 'slug'),

            'ancestor' => new AncestorStrategy($this->entityManager),
            'tag' => new JsonArrayStrategy('tags'),
        ];
    }
}
