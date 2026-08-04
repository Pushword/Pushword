<?php

namespace Pushword\Newsletter\Query;

use DateTimeImmutable;
use Pushword\Core\Query\Field\FieldRegistry;
use Pushword\Core\Query\Field\FieldStrategy;
use Pushword\Core\Query\Field\Strategy\ColumnStrategy;
use Pushword\Core\Query\Field\Strategy\JsonArrayStrategy;
use Pushword\Core\Query\Field\Strategy\JsonPropertyStrategy;
use Pushword\Core\Query\Field\Strategy\OptionalColumnStrategy;

/**
 * What is filterable on a `Contact`.
 *
 * The counterpart of {@see \Pushword\Core\Query\PageFieldRegistry}, behind the
 * same interface: a segment and a page rule are two vocabularies over one
 * compiler, so neither can drift into its own semantics for tags — that strategy
 * is literally the same object. A custom property is read through the same one
 * too, wrapped by {@see ContactPropertyStrategy} to add the duration operators:
 * what `=` means on a property is shared, what `olderThan` means is a segment's.
 *
 * Built per resolution, because a duration only means something against an
 * instant, and every condition of one rule must read the same one.
 */
final class ContactFieldRegistry implements FieldRegistry
{
    /** @var array<string, FieldStrategy>|null */
    private ?array $strategies = null;

    public function __construct(private readonly DateTimeImmutable $now)
    {
    }

    public function strategy(string $field): ?FieldStrategy
    {
        if (str_starts_with($field, JsonPropertyStrategy::PREFIX)) {
            return JsonPropertyStrategy::PREFIX === $field ? null : new ContactPropertyStrategy('customProperties', $this->now);
        }

        return $this->strategies()[$field] ?? null;
    }

    public function fields(): array
    {
        return [...array_keys($this->strategies()), JsonPropertyStrategy::PREFIX.'<key>'];
    }

    /** A contact is never read relative to a page being rendered. */
    public function contextualFields(): array
    {
        return [];
    }

    /** @return array<string, FieldStrategy> */
    private function strategies(): array
    {
        return $this->strategies ??= [
            'tag' => new JsonArrayStrategy('tags'),
            'locale' => new ColumnStrategy('locale'),
            // Nullable, so `isSet` / `isNotSet` are the operators that matter:
            // "everybody I can only phone" is a segment somebody writes.
            'email' => new OptionalColumnStrategy('email'),
            'phone' => new OptionalColumnStrategy('phone'),
            'createdAt' => new DurationThresholdStrategy('createdAt', $this->now),
            'confirmedAt' => new DurationThresholdStrategy('confirmedAt', $this->now),
        ];
    }
}
