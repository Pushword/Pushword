<?php

namespace Pushword\Core\Query\Field;

/**
 * What is filterable on one entity, and how.
 *
 * One registry per entity filtered — pages in core, contacts in the newsletter —
 * behind one interface, so {@see \Pushword\Core\Query\QueryCompiler} walks a
 * tree without knowing which entity it is about.
 */
interface FieldRegistry
{
    /** Null when the field is not filterable here — the caller decides whether that is an error. */
    public function strategy(string $field): ?FieldStrategy;

    /**
     * The names a surface may offer, for documentation and for error messages.
     * `prop.*` appears as its prefix.
     *
     * @return list<string>
     */
    public function fields(): array;

    /**
     * Fields a surface can only honour when it knows which page is being
     * rendered — `children`, `related`… They are resolved while parsing a
     * `pages_list` search and never reach the compiler, so a surface without a
     * current page has to refuse them by name rather than silently miss them.
     *
     * @return list<string>
     */
    public function contextualFields(): array;
}
