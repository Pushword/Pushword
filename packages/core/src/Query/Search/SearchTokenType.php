<?php

namespace Pushword\Core\Query\Search;

/**
 * What {@see SearchLexer} emits. A token is a `[SearchTokenType, string]` pair;
 * only a term carries text worth reading.
 */
enum SearchTokenType
{
    case Term;
    case Conjunction;
    case OpenGroup;
    case CloseGroup;
}
