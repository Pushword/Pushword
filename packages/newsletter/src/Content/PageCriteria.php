<?php

namespace Pushword\Newsletter\Content;

use Override;
use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Group;
use Pushword\Core\Query\PageFieldRegistry;
use Pushword\Core\Query\Search\PageSearchVocabulary;
use Pushword\Core\Query\Search\SearchException;
use Pushword\Core\Query\Search\SearchParser;
use Pushword\Newsletter\Criteria\AbstractCriteria;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;

/**
 * The page language: which published pages an automation on the
 * {@see \Pushword\Newsletter\Trigger\Source\PageTriggerSource} reacts to. Same
 * shape as {@see SegmentCriteria} — a list of conditions, all of
 * which must hold, or a `{"any": [...]}` group where a single one is enough, and
 * a child of either may be a group of its own — so there is one thing to learn
 * for both sides of a trigger:
 *
 *     [
 *       {"field": "ancestor",         "op": "=",          "value": "blog"},
 *       {"field": "template",         "op": "=",          "value": "article.html.twig"},
 *       {"field": "prop.noNewsletter","op": "isNotSet"}
 *     ]
 *
 * It says *which* pages, never *when*: the wait is the trigger's own delay, and
 * `publishedAt` is refused for saying so twice.
 *
 * The same fields answer to a `pages_list` search — a trigger accepts one
 * instead of the JSON — so the words are the ones an editor already writes:
 *
 *     ancestor:blog AND template:article.html.twig
 *
 * Reach for what already groups pages before reaching for `any`, the two axes a
 * `pages_list` search leans on: the tree — `parent` names one rubric, `ancestor`
 * the section it belongs to — and `tag`. Either keeps a blog split in rubrics
 * down to a single condition, and keeps covering the rubric added next month,
 * which an enumeration does not.
 *
 * A malformed list raises {@see SegmentException},
 * the same way a malformed segment does — one grammar, one kind of mistake, one
 * thing to catch.
 */
final class PageCriteria extends AbstractCriteria
{
    public const string SUBJECT = 'a page';

    public const bool ACCEPTS_SEARCH = true;

    /** Operators accepted for each plain field. `prop.*` is handled apart. */
    public const array FIELD_OPERATORS = [
        'slug' => ['startsWith', 'notStartsWith'],
        'template' => ['=', '!='],
        'parent' => ['=', '!='],
        'ancestor' => ['=', '!='],
        // A page carries tags as a contact does, so it reads as one on the
        // segment side: `has`, not `=`.
        'tag' => ['has', 'hasNot'],
    ];

    #[Override]
    protected static function refusals(): array
    {
        return [
            ...array_fill_keys(
                PageFieldRegistry::CONTEXTUAL_FIELDS,
                'names pages relative to the one being rendered, and a trigger has no current page',
            ),
            'publishedAt' => "is already bounded, by the trigger's triggerFrom and by the tick it runs in",
        ];
    }

    /** @return class-string<AbstractCriteria> */
    protected static function neighbour(): string
    {
        return SegmentCriteria::class;
    }

    /**
     * A rule may be written as a `pages_list` search — the words an editor
     * already uses in a template, parsed by the same parser.
     *
     * It is not a second vocabulary: what it parses into is validated against
     * {@see FIELD_OPERATORS} like any rule, so a search reaching for something a
     * trigger has no business filtering on — `title:`, a comment, the current
     * page's children — is refused by name.
     */
    protected static function fromSearch(string $search): Group|Condition
    {
        try {
            return new SearchParser(new PageSearchVocabulary(contextual: false))->parse($search);
        } catch (SearchException $searchException) {
            throw new SegmentException($searchException->getMessage(), 0, $searchException);
        }
    }
}
