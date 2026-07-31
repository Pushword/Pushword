<?php

namespace Pushword\Core\Query;

/**
 * What a user-supplied value needs before it goes into a LIKE pattern.
 *
 * `_` and `%` are wildcards, and both are legal in a slug, a tag or a property
 * value — an unescaped one silently widens the match instead of failing.
 * Escaping them is not enough on its own: SQLite gives LIKE no escape character
 * at all unless one is named, where MySQL takes the backslash for granted. The
 * same pattern would then match nothing on one and work on the other, so a
 * pattern built here only means what it says when the comparison carries
 * {@see ESCAPE_CHARACTER} in an explicit `ESCAPE` clause.
 */
final class LikePattern
{
    /** Not a backslash, precisely so that neither engine's default applies. */
    public const string ESCAPE_CHARACTER = '!';

    /** Every special character gets the escape prefix; nothing else is touched. */
    public static function escape(string $value): string
    {
        $special = [self::ESCAPE_CHARACTER, '%', '_'];

        return str_replace($special, array_map(static fn (string $c): string => self::ESCAPE_CHARACTER.$c, $special), $value);
    }

    /**
     * The comparison, escape clause included — the two halves are useless apart,
     * so they are written in one place.
     */
    public static function comparison(string $left, string $operator, string $parameter): string
    {
        return \sprintf("%s %s :%s ESCAPE '%s'", $left, $operator, $parameter, self::ESCAPE_CHARACTER);
    }
}
