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
 * `JSON_SCALAR(column, '$.path')` — read a JSON member as a comparable SQL scalar.
 *
 * {@see JsonExtractFunction} is enough for numbers, but a string member comes
 * back quoted on MySQL/MariaDB (`"tmb"`) and unquoted on SQLite (`tmb`), so the
 * same DQL comparison would match on one platform and not the other. This wraps
 * the MySQL side in JSON_UNQUOTE so a custom-property comparison behaves
 * identically on both.
 */
class JsonScalarFunction extends FunctionNode
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
            return \sprintf('JSON_UNQUOTE(%s)', $extract);
        }

        return $extract;
    }
}
