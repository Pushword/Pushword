<?php

namespace Pushword\Core\Repository\DQL;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;
use Override;

/**
 * `JSON_NUMBER(column, '$.path')` — read a JSON member as a comparable number.
 *
 * SQLite's json_extract already yields typed values, so a JSON number compares
 * and sorts numerically there. MySQL/MariaDB's JSON_UNQUOTE(JSON_EXTRACT())
 * always yields a string, which sorts lexically (10 before 9) — the explicit
 * CAST is what makes `< > <= >=` and ORDER BY agree across platforms.
 */
class JsonNumberFunction extends FunctionNode
{
    private Node|string $column;

    private Node $path;

    #[Override]
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->column = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->path = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    #[Override]
    public function getSql(SqlWalker $sqlWalker): string
    {
        $column = $this->column instanceof Node ? $this->column->dispatch($sqlWalker) : $this->column;
        $extract = \sprintf('JSON_EXTRACT(%s, %s)', $column, $this->path->dispatch($sqlWalker));

        if ($sqlWalker->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return \sprintf('CAST(JSON_UNQUOTE(%s) AS DECIMAL(20, 6))', $extract);
        }

        return $extract;
    }
}
