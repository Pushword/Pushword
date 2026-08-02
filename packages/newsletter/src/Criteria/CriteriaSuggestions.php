<?php

namespace Pushword\Newsletter\Criteria;

/**
 * The values that already exist for the fields of one criteria language — the
 * tags in use, the templates a site renders — so a rule is picked from what is
 * there rather than remembered.
 *
 * Suggestions and not choices: a rule may name a tag nobody carries yet, and
 * refusing it would make it impossible to write an automation before the content
 * it waits for exists.
 *
 * A bundle adding a {@see \Pushword\Newsletter\Trigger\TriggerSource} with a
 * vocabulary of its own implements this and tags the service
 * `pushword.newsletter.criteria_suggestions` to fill its own dropdowns; one that
 * does not is offered plain text boxes, which the language still validates.
 */
interface CriteriaSuggestions
{
    /** @return class-string<AbstractCriteria> */
    public function criteria(): string;

    /**
     * The values themselves, in whatever order they come out: deduplicating and
     * ordering what is offered is {@see CriteriaVocabulary}'s, so an
     * implementation is only ever a query.
     *
     * @param string[] $hosts the sites in scope, empty for every one
     *
     * @return array<string, string[]> by field name, {@see AbstractCriteria::PROP_PREFIX} holding property keys
     */
    public function suggest(array $hosts): array;
}
