<?php

namespace Pushword\Core\Repository\DQL;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;
use Override;

/**
 * `JSON_TEXT(column)` — expose the serialized JSON for LIKE-based compatibility
 * queries. SQLite and MySQL accept LIKE on their JSON storage directly;
 * PostgreSQL's native json type requires an explicit text cast.
 */
final class JsonTextFunction extends FunctionNode
{
    private Node|string $column;

    #[Override]
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->column = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    #[Override]
    public function getSql(SqlWalker $sqlWalker): string
    {
        $column = $this->column instanceof Node ? $this->column->dispatch($sqlWalker) : $this->column;

        return $sqlWalker->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? \sprintf('CAST(%s AS TEXT)', $column)
            : $column;
    }
}
