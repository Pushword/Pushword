<?php

namespace Pushword\Core\Query;

/**
 * How a {@see Group} joins its children.
 *
 * The two surfaces spell it differently — `AND`/`OR` in a `pages_list` search,
 * `all`/`any` in the newsletter's JSON — so the enum carries both: the case is
 * what the JSON calls it, the value is what the search language and the DQL call
 * it.
 */
enum Conjunction: string
{
    case All = 'AND';
    case Any = 'OR';
}
