<?php

namespace Pushword\Core\Query\Search;

use Exception;

/**
 * A search that cannot be read at all: an unclosed parenthesis, a dangling
 * operator, an empty group.
 *
 * Only *structural* mistakes land here. An unknown prefix never does — the
 * engine cannot tell `type:product`, a namespaced tag used in production, from
 * `tags:blog`, a typo for `tag:blog`, so both stay tag searches and a lint
 * command catches the dead one by running it.
 */
final class SearchException extends Exception
{
}
