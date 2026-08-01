<?php

namespace Pushword\Core\Query\Search;

use Pushword\Core\Entity\Page;
use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Conjunction;
use Pushword\Core\Query\Field\Strategy\JsonPropertyStrategy;
use Pushword\Core\Query\Group;
use Pushword\Core\Query\PageFieldRegistry;

/**
 * What one term of a `pages_list` search means.
 *
 * The prefixes, in the order they are tried — the order matters, since a term
 * matching none of them is a tag and a term is only ever tried once.
 *
 * @see packages/docs/content/pages-list.md
 */
final readonly class PageSearchVocabulary
{
    /**
     * @param bool $contextual whether `children` and its siblings mean anything
     *                         here. A `pages_list` renders inside a page, even
     *                         when there is none to speak of — the search then
     *                         matches nothing, which is the right answer. A rule
     *                         stored in the database is read outside any page,
     *                         and there the same term is a mistake worth naming
     */
    public function __construct(
        private ?Page $currentPage = null,
        private bool $contextual = true,
    ) {
    }

    /** @throws SearchException */
    public function term(string $term): Group|Condition
    {
        $term = trim($term);

        return $this->contextual($term)
            ?? $this->prefixed($term)
            // Anything else is a tag, and will stay one: `type:product` is a
            // namespaced tag in production, and no parser can tell it from a
            // mistyped prefix.
            ?? $this->tag($term);
    }

    /**
     * The searches that mean something only relative to the page being rendered.
     * Case insensitive, and load-bearing so: sites write `CHILDREN`.
     */
    private function contextual(string $term): Group|Condition|null
    {
        $currentPage = $this->currentPage;

        if (! $this->contextual) {
            if (\in_array(strtolower($term), PageFieldRegistry::CONTEXTUAL_FIELDS, true)) {
                throw new SearchException(\sprintf('"%s" names pages relative to the one being rendered, and there is no current page here.', $term));
            }

            return null;
        }

        return match (strtolower($term)) {
            'related' => $this->related($currentPage),
            'children' => new Condition('parentPage', '=', $currentPage->id ?? 0),
            'sisters', 'parent_children' => new Condition('parentPage', '=', $currentPage?->parentPage->id ?? 0),
            'grandchildren', 'children_children' => new Condition(
                'parentPage',
                'IN',
                $currentPage?->childrenPages->map(static fn (Page $page): int => $page->id ?? 0)->toArray() ?? [],
            ),
            default => null,
        };
    }

    /**
     * Sister pages, bounded by the current id — the heuristic being that a page
     * created shortly before this one is about the same thing. With no parent
     * there are no sisters, and only the bound is left.
     */
    private function related(?Page $currentPage): Group|Condition
    {
        $bound = new Condition('id', '<', ($currentPage->id ?? 0) + 3);
        $parentPage = $currentPage?->parentPage;

        if (null === $parentPage) {
            return $bound;
        }

        return new Group(Conjunction::All, [new Condition('parentPage', '=', $parentPage->id ?? 0), $bound]);
    }

    private function prefixed(string $term): Group|Condition|null
    {
        if (null !== ($value = $this->after($term, 'related:comment:'))) {
            return new Group(Conjunction::All, [
                $this->comment($value),
                new Condition('id', '<', ($this->currentPage->id ?? 0) + 3),
            ]);
        }

        if (null !== ($value = $this->after($term, 'comment:'))) {
            return $this->comment($value);
        }

        // `title:` searches the two titles a page carries; `content:` adds the body.
        foreach (['title:' => false, 'content:' => true] as $prefix => $withBody) {
            if (null !== ($value = $this->after($term, $prefix))) {
                return $this->titles($value, $withBody);
            }
        }

        foreach (['slug:', 'page:'] as $prefix) {
            if (null !== ($value = $this->after($term, $prefix))) {
                // Not escaped, on purpose: `slug:%partial%` is documented.
                return new Condition('slug', 'LIKE', $value);
            }
        }

        // Fields the registry compiles: the prefix names the field, and what it
        // means — a join, a section walk, a JSON path — is decided there.
        foreach (['template:' => 'template', 'parent:' => 'parent', 'ancestor:' => 'ancestor', 'locale:' => 'locale'] as $prefix => $field) {
            if (null !== ($value = $this->after($term, $prefix))) {
                return new Condition($field, '=', $value);
            }
        }

        if (null !== ($value = $this->after($term, 'tag:'))) {
            return $this->tag($value);
        }

        // `customProperty:` is the older spelling of the same thing.
        foreach (['prop:', 'customProperty:'] as $prefix) {
            if (null !== ($value = $this->after($term, $prefix))) {
                return $this->property($value);
            }
        }

        return null;
    }

    /** What follows a prefix, or null when the term does not carry it. */
    private function after(string $term, string $prefix): ?string
    {
        return str_starts_with($term, $prefix) ? substr($term, \strlen($prefix)) : null;
    }

    private function titles(string $value, bool $withBody): Group
    {
        $pattern = '%'.$value.'%';
        $children = [new Condition('h1', 'LIKE', $pattern), new Condition('title', 'LIKE', $pattern)];

        if ($withBody) {
            $children[] = new Condition('mainContent', 'LIKE', $pattern);
        }

        return new Group(Conjunction::Any, $children);
    }

    /** `key:value`. Without the separator there is no property to read, so the whole term stays a tag. */
    private function property(string $keyAndValue): ?Condition
    {
        $separator = strpos($keyAndValue, ':');

        if (false === $separator) {
            return null;
        }

        return new Condition(
            JsonPropertyStrategy::PREFIX.substr($keyAndValue, 0, $separator),
            '=',
            substr($keyAndValue, $separator + 1),
        );
    }

    private function comment(string $value): Condition
    {
        return new Condition('mainContent', 'LIKE', '%<!--'.$value.'-->%');
    }

    private function tag(string $value): Condition
    {
        return new Condition('tag', 'has', $value);
    }
}
